<?php

namespace App\Http\Controllers;

use App\Models\BorrowingItem;
use App\Models\BorrowingRequest;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\ReturnTransaction;
use App\Models\Role;
use App\Models\StockMovement;
use App\Services\AuditLogService;
use App\Services\InventoryStockService;
use App\Services\NotificationService;
use App\Services\OverdueMonitoringService;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BorrowingController extends Controller
{
    public function __construct(
        protected InventoryStockService $stockService,
        protected NotificationService $notifications,
        protected AuditLogService $auditLog,
        protected OverdueMonitoringService $overdueService,
        protected SettingsService $settings
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $isOfficer = ! $user->hasPermission('borrowings.view_all');
        $tab = (string) $request->input('tab', 'all');

        $query = BorrowingRequest::query()
            ->with([
                'requester',
                'approver',
                'items.inventoryItem',
                'returnTransactions.receiver',
                'returnTransactions.inventoryItem',
                'returnTransactions.conditionOnReturn',
            ]);

        if ($isOfficer) {
            $query->where('requester_user_id', $user->id);
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('transaction_code', 'like', "%{$search}%")
                    ->orWhere('borrower_name', 'like', "%{$search}%")
                    ->orWhere('school_id', 'like', "%{$search}%")
                    ->orWhere('department_organization', 'like', "%{$search}%");
            });
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('date_borrowed', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('date_borrowed', '<=', $dateTo);
        }

        switch ($tab) {
            case 'pending':
                $query->where('status', BorrowingRequest::STATUS_PENDING);
                break;
            case 'active':
                $query->whereIn('status', [
                    BorrowingRequest::STATUS_APPROVED,
                    BorrowingRequest::STATUS_PARTIALLY_RETURNED,
                    BorrowingRequest::STATUS_OVERDUE,
                ]);
                break;
            case 'overdue':
                $query->where(function ($q): void {
                    $q->where('status', BorrowingRequest::STATUS_OVERDUE)
                        ->orWhere(function ($sub): void {
                            $sub->whereIn('status', [
                                BorrowingRequest::STATUS_APPROVED,
                                BorrowingRequest::STATUS_PARTIALLY_RETURNED,
                            ])->whereDate('expected_return_date', '<', Carbon::today());
                        });
                });
                break;
            case 'returned':
                $query->where('status', BorrowingRequest::STATUS_RETURNED);
                break;
            case 'rejected':
                $query->whereIn('status', [BorrowingRequest::STATUS_REJECTED, BorrowingRequest::STATUS_CANCELLED]);
                break;
        }

        $borrowings = ($tab === 'history'
            ? $query->latest('updated_at')
            : $query->latest('created_at')
        )->paginate(15)->withQueryString();

        $pendingInspections = collect();
        if ($user->hasPermission('borrowings.process_return')) {
            $pendingInspections = ReturnTransaction::with([
                'borrowingRequest',
                'inventoryItem',
                'conditionOnReturn',
                'receiver',
            ])
                ->where('flagged_for_inspection', true)
                ->where('inspection_status', ReturnTransaction::INSPECTION_PENDING)
                ->latest()
                ->get();
        }

        return view('borrowings.index', [
            'borrowings' => $borrowings,
            'pendingInspections' => $pendingInspections,
            'conditions' => InventoryCondition::orderBy('severity_rank')->get(),
            'tab' => $tab,
            'isOfficer' => $isOfficer,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            abort(403, 'Administrators cannot submit borrowing requests.');
        }

        $defaultDays = max(1, (int) $this->settings->get('default_borrowing_days', 7));

        $availableItems = InventoryItem::query()
            ->with(['category', 'location'])
            ->where('status', InventoryItem::STATUS_AVAILABLE)
            ->where('available_quantity', '>', 0)
            ->orderBy('name')
            ->get();

        return view('borrowings.create', [
            'availableItems' => $availableItems,
            'preselectedItemId' => $request->input('item_id'),
            'user' => $user,
            'defaultReturnDate' => now()->addDays($defaultDays)->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->isAdmin()) {
            abort(403, 'Administrators cannot submit borrowing requests.');
        }

        $validated = $request->validate([
            'borrower_name' => ['required', 'string', 'max:150'],
            'school_id' => ['required', 'string', 'max:60'],
            'department_organization' => ['required', 'string', 'max:150'],
            'purpose' => ['required', 'string', 'min:5'],
            'request_date' => ['required', 'date'],
            'date_borrowed' => ['required', 'date'],
            'expected_return_date' => ['required', 'date', 'after_or_equal:date_borrowed'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'items.*.quantity_requested' => ['required', 'integer', 'min:1'],
        ]);

        // Aggregate duplicate item rows safely and validate stock
        $requestedQuantities = [];
        foreach ($validated['items'] as $row) {
            $id = (int) $row['inventory_item_id'];
            $qty = (int) $row['quantity_requested'];
            $requestedQuantities[$id] = ($requestedQuantities[$id] ?? 0) + $qty;
        }

        $itemsMap = InventoryItem::whereIn('id', array_keys($requestedQuantities))->get()->keyBy('id');

        foreach ($requestedQuantities as $itemId => $qty) {
            $item = $itemsMap->get($itemId);
            if (! $item || $item->status !== InventoryItem::STATUS_AVAILABLE) {
                throw ValidationException::withMessages([
                    'items' => ($item?->name ?? 'Selected item') . ' is currently not available for borrowing.',
                ]);
            }
            if ($qty > $item->available_quantity) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient available stock for {$item->name} (requested: {$qty}, available: {$item->available_quantity}).",
                ]);
            }
        }

        $user = $request->user();

        $borrowing = DB::transaction(function () use ($validated, $requestedQuantities, $user): BorrowingRequest {
            $requestModel = BorrowingRequest::create([
                'transaction_code' => 'BOR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
                'requester_user_id' => $user->id,
                'borrower_name' => $validated['borrower_name'],
                'school_id' => $validated['school_id'],
                'department_organization' => $validated['department_organization'],
                'borrower_contact' => $user->phone,
                'purpose' => $validated['purpose'],
                'request_date' => $validated['request_date'],
                'date_borrowed' => $validated['date_borrowed'],
                'expected_return_date' => $validated['expected_return_date'],
                'status' => BorrowingRequest::STATUS_PENDING,
                'remarks' => null,
            ]);

            foreach ($requestedQuantities as $itemId => $qty) {
                BorrowingItem::create([
                    'borrowing_request_id' => $requestModel->id,
                    'inventory_item_id' => $itemId,
                    'quantity_requested' => $qty,
                    'status' => 'pending',
                ]);
            }

            $this->notifications->sendToRoles(
                [Role::SLUG_ADMIN],
                'borrowing_requested',
                'New Borrowing Request Submitted',
                "{$requestModel->borrower_name} submitted borrowing request {$requestModel->transaction_code}.",
                'info',
                route('borrowings.show', $requestModel),
                $requestModel,
                $user->id
            );

            $this->auditLog->log(
                action: 'borrowing.requested',
                module: 'borrowings',
                auditable: $requestModel,
                newValues: [
                    'transaction_code' => $requestModel->transaction_code,
                    'borrower_name' => $requestModel->borrower_name,
                    'items' => $requestedQuantities,
                ],
                description: "Submitted borrowing request {$requestModel->transaction_code} for {$requestModel->borrower_name}.",
                recordLabel: $requestModel->transaction_code,
                actor: $user
            );

            return $requestModel;
        });

        return redirect()
            ->route('borrowings.show', $borrowing)
            ->with('success', "Borrowing request {$borrowing->transaction_code} submitted and awaiting approval.");
    }

    public function show(Request $request, BorrowingRequest $borrowing): View
    {
        $user = $request->user();
        if (! $user->hasPermission('borrowings.view_all') && $borrowing->requester_user_id !== $user->id) {
            $this->auditLog->log(
                action: 'ACCESS_DENIED',
                module: 'security',
                auditable: $borrowing,
                description: "IDOR access denied: {$user->email} attempted to view borrowing {$borrowing->transaction_code}.",
                recordLabel: $borrowing->transaction_code,
                actor: $user
            );

            abort(403, 'You are not authorized to view this borrowing request.');
        }

        $borrowing->load([
            'requester',
            'approver',
            'items.inventoryItem.condition',
            'items.inventoryItem.category',
            'items.conditionOnBorrow',
            'returnTransactions.inventoryItem',
            'returnTransactions.conditionOnReturn',
            'returnTransactions.receiver',
            'returnTransactions.inspector',
        ]);

        return view('borrowings.show', [
            'borrowing' => $borrowing,
            'conditions' => InventoryCondition::orderBy('severity_rank')->get(),
        ]);
    }

    public function approve(Request $request, BorrowingRequest $borrowing): RedirectResponse
    {
        if ($borrowing->status !== BorrowingRequest::STATUS_PENDING) {
            return back()->with('error', 'Only pending borrowing requests can be approved.');
        }

        $request->validate([
            'count_verified' => ['accepted'],
        ], [
            'count_verified.accepted' => 'Please double-check and confirm item count and condition before releasing equipment.',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($borrowing, $user): void {
            $borrowing->load('items');

            foreach ($borrowing->items as $borrowingItem) {
                $item = InventoryItem::lockForUpdate()->findOrFail($borrowingItem->inventory_item_id);

                if ($item->status !== InventoryItem::STATUS_AVAILABLE || $item->available_quantity < $borrowingItem->quantity_requested) {
                    throw ValidationException::withMessages([
                        'approval' => "Cannot approve: {$item->name} only has {$item->available_quantity} available unit(s).",
                    ]);
                }

                $this->stockService->recordMovement(
                    item: $item,
                    movementType: StockMovement::TYPE_BORROWING,
                    quantity: $borrowingItem->quantity_requested,
                    actor: $user,
                    remarks: "Approved borrowing {$borrowing->transaction_code} for {$borrowing->borrower_name}.",
                    options: [
                        'reference' => $borrowing,
                    ]
                );

                $borrowingItem->update([
                    'quantity_approved' => $borrowingItem->quantity_requested,
                    'condition_on_borrow_id' => $item->condition_id,
                    'status' => 'borrowed',
                ]);
            }

            $borrowing->update([
                'status' => BorrowingRequest::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'count_verified' => true,
            ]);

            $this->notifications->sendToUser(
                $borrowing->requester_user_id,
                'borrowing_approved',
                'Borrowing Request Approved',
                "Your borrowing request {$borrowing->transaction_code} has been approved.",
                'success',
                route('borrowings.show', $borrowing),
                $borrowing
            );

            $this->auditLog->log(
                action: 'borrowing.approved',
                module: 'borrowings',
                auditable: $borrowing,
                oldValues: ['status' => BorrowingRequest::STATUS_PENDING],
                newValues: ['status' => BorrowingRequest::STATUS_APPROVED],
                description: "Approved borrowing request {$borrowing->transaction_code}.",
                recordLabel: $borrowing->transaction_code,
                actor: $user
            );
        });

        // Re-check immediately in case borrowed with a past-due date
        $this->overdueService->run();

        return redirect()
            ->route('borrowings.show', $borrowing)
            ->with('success', "Borrowing request {$borrowing->transaction_code} approved and stock deducted.");
    }

    public function reject(Request $request, BorrowingRequest $borrowing): RedirectResponse
    {
        if ($borrowing->status !== BorrowingRequest::STATUS_PENDING) {
            return back()->with('error', 'Only pending borrowing requests can be rejected.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3'],
        ]);

        $user = $request->user();

        $borrowing->update([
            'status' => BorrowingRequest::STATUS_REJECTED,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $borrowing->items()->update(['status' => 'rejected']);

        $this->notifications->sendToUser(
            $borrowing->requester_user_id,
            'borrowing_rejected',
            'Borrowing Request Rejected',
            "Your borrowing request {$borrowing->transaction_code} was rejected: {$validated['rejection_reason']}",
            'danger',
            route('borrowings.show', $borrowing),
            $borrowing
        );

        $this->auditLog->log(
            action: 'borrowing.rejected',
            module: 'borrowings',
            auditable: $borrowing,
            oldValues: ['status' => BorrowingRequest::STATUS_PENDING],
            newValues: ['status' => BorrowingRequest::STATUS_REJECTED, 'rejection_reason' => $validated['rejection_reason']],
            description: "Rejected borrowing request {$borrowing->transaction_code}.",
            recordLabel: $borrowing->transaction_code,
            actor: $user
        );

        return redirect()
            ->route('borrowings.show', $borrowing)
            ->with('success', "Borrowing request {$borrowing->transaction_code} rejected.");
    }

    public function submitReturnRequest(Request $request, BorrowingRequest $borrowing): RedirectResponse
    {
        $user = $request->user();
        if ($user->isAdmin() || $borrowing->requester_user_id !== $user->id) {
            abort(403, 'Only the borrowing SSC officer can return this equipment.');
        }

        if (! $borrowing->isActiveLoan()) {
            return back()->with('error', 'Only active borrowed transactions can be returned.');
        }

        DB::transaction(function () use ($borrowing, $user): void {
            $borrowingItems = BorrowingItem::lockForUpdate()
                ->where('borrowing_request_id', $borrowing->id)
                ->get();

            $fallbackConditionId = InventoryCondition::where('slug', 'good')->value('id')
                ?? InventoryCondition::value('id');

            foreach ($borrowingItems as $bi) {
                $remaining = $bi->remainingQuantity();
                if ($remaining <= 0) {
                    continue;
                }

                $item = InventoryItem::lockForUpdate()->findOrFail($bi->inventory_item_id);
                $conditionId = $bi->condition_on_borrow_id ?? $item->condition_id ?? $fallbackConditionId;

                $returnTx = ReturnTransaction::create([
                    'return_code' => 'RET-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
                    'borrowing_request_id' => $borrowing->id,
                    'borrowing_item_id' => $bi->id,
                    'inventory_item_id' => $item->id,
                    'quantity_returned' => $remaining,
                    'actual_return_date' => now()->toDateString(),
                    'condition_on_return_id' => $conditionId,
                    'is_damaged' => false,
                    'is_missing' => false,
                    'flagged_for_inspection' => false,
                    'received_by' => $user->id,
                    'remarks' => 'Direct automatic return to inventory.',
                    'count_verified' => true,
                ]);

                $this->stockService->recordMovement(
                    item: $item,
                    movementType: StockMovement::TYPE_RETURNING,
                    quantity: $remaining,
                    actor: $user,
                    remarks: "Returned directly to inventory ({$returnTx->return_code} / {$borrowing->transaction_code}).",
                    options: [
                        'reference' => $returnTx,
                    ]
                );

                $bi->quantity_returned += $remaining;
                $bi->status = 'returned';
                $bi->save();
            }

            $borrowing->status = BorrowingRequest::STATUS_RETURNED;
            $borrowing->actual_return_date = now()->toDateString();
            $borrowing->return_requested_at = now();
            $borrowing->save();

            $this->notifications->sendToRoles(
                [Role::SLUG_ADMIN],
                'borrowing_returned',
                'Borrowed Equipment Returned',
                "{$borrowing->borrower_name} returned borrowed items for {$borrowing->transaction_code} back to inventory.",
                'success',
                route('borrowings.show', $borrowing),
                $borrowing,
                $user->id
            );

            $this->auditLog->log(
                action: 'borrowing.returned',
                module: 'borrowings',
                auditable: $borrowing,
                description: "Returned all borrowed items for {$borrowing->transaction_code} directly to inventory.",
                recordLabel: $borrowing->transaction_code,
                actor: $user
            );
        });

        return redirect()
            ->route('borrowings.index')
            ->with('success', "All borrowed items for {$borrowing->transaction_code} have been returned to inventory.");
    }

    public function sendReturnNotice(Request $request, BorrowingRequest $borrowing): RedirectResponse
    {
        if (! $borrowing->isActiveLoan()) {
            return back()->with('error', 'Return notices can only be sent for active borrowed transactions.');
        }

        $this->notifications->sendToUser(
            $borrowing->requester_user_id,
            'return_reminder',
            'Notice to Return Borrowed Equipment',
            "Admin reminded you to return the borrowed equipment for {$borrowing->transaction_code} (Due: {$borrowing->expected_return_date->format('M d, Y')}).",
            $borrowing->isOverdue() ? 'danger' : 'warning',
            route('borrowings.show', $borrowing),
            $borrowing
        );

        $this->auditLog->log(
            action: 'borrowing.return_notice_sent',
            module: 'borrowings',
            auditable: $borrowing,
            description: "Sent notice to return for {$borrowing->transaction_code} to {$borrowing->borrower_name}.",
            recordLabel: $borrowing->transaction_code,
            actor: $request->user()
        );

        return back()->with('success', "Notice to return sent to {$borrowing->borrower_name} ({$borrowing->transaction_code}).");
    }

    public function destroy(Request $request, BorrowingRequest $borrowing): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('borrowings.view_all') && $borrowing->requester_user_id !== $user->id) {
            abort(403, 'You are not authorized to delete this borrowing history record.');
        }

        if ($borrowing->isActiveLoan()) {
            return back()->with('error', 'Active borrowed items cannot be deleted until returned.');
        }

        $code = $borrowing->transaction_code;
        $borrowing->delete();

        $this->auditLog->log(
            action: 'borrowing.history_deleted',
            module: 'borrowings',
            auditable: $borrowing,
            description: "Deleted borrowing history record {$code}.",
            recordLabel: $code,
            actor: $user
        );

        return back()->with('success', "History record {$code} deleted.");
    }

    public function clearHistory(Request $request): RedirectResponse
    {
        $user = $request->user();

        $query = BorrowingRequest::query()->whereIn('status', [
            BorrowingRequest::STATUS_RETURNED,
            BorrowingRequest::STATUS_REJECTED,
            BorrowingRequest::STATUS_CANCELLED,
        ]);

        if (! $user->hasPermission('borrowings.view_all')) {
            $query->where('requester_user_id', $user->id);
        }

        $count = $query->delete();

        if ($count > 0) {
            $this->auditLog->log(
                action: 'borrowing.history_cleared',
                module: 'borrowings',
                description: "Cleared {$count} completed borrowing history record(s).",
                actor: $user
            );
        }

        return redirect()
            ->route('borrowings.index', ['tab' => 'history'])
            ->with('success', $count > 0 ? "Cleared {$count} history record(s)." : 'No completed history records to clear.');
    }
}
