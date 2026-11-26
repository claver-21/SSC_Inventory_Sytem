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
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReturnController extends Controller
{
    public function __construct(
        protected InventoryStockService $stockService,
        protected NotificationService $notifications,
        protected AuditLogService $auditLog
    ) {
    }

    public function store(Request $request, BorrowingRequest $borrowing): RedirectResponse
    {
        if (! $borrowing->isActiveLoan()) {
            return back()->with('error', 'Returns can only be processed for active or overdue borrowings.');
        }

        $validated = $request->validate([
            'borrowing_item_id' => ['required', 'exists:borrowing_items,id'],
            'quantity_returned' => ['required', 'integer', 'min:1'],
            'actual_return_date' => ['required', 'date'],
            'condition_on_return_id' => ['required', 'exists:inventory_conditions,id'],
            'return_outcome' => ['required', Rule::in(['good', 'damaged', 'missing'])],
            'remarks' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($borrowing, $validated, $user): void {
            $borrowingItem = BorrowingItem::lockForUpdate()
                ->where('borrowing_request_id', $borrowing->id)
                ->findOrFail($validated['borrowing_item_id']);

            $remaining = $borrowingItem->remainingQuantity();
            $qty = (int) $validated['quantity_returned'];

            if ($qty > $remaining) {
                throw ValidationException::withMessages([
                    'quantity_returned' => "Cannot return {$qty} unit(s); only {$remaining} unit(s) remain outstanding.",
                ]);
            }

            $condition = InventoryCondition::findOrFail($validated['condition_on_return_id']);
            $outcome = $validated['return_outcome'];
            $isMissing = $outcome === 'missing';
            $isDamaged = $outcome === 'damaged' || (! $isMissing && in_array($condition->slug, ['poor', 'non_functional'], true));
            $flaggedForInspection = $isDamaged;

            $item = InventoryItem::lockForUpdate()->findOrFail($borrowingItem->inventory_item_id);

            $returnTx = ReturnTransaction::create([
                'return_code' => 'RET-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
                'borrowing_request_id' => $borrowing->id,
                'borrowing_item_id' => $borrowingItem->id,
                'inventory_item_id' => $item->id,
                'quantity_returned' => $qty,
                'actual_return_date' => $validated['actual_return_date'],
                'condition_on_return_id' => $condition->id,
                'is_damaged' => $isDamaged,
                'is_missing' => $isMissing,
                'flagged_for_inspection' => $flaggedForInspection,
                'inspection_status' => $flaggedForInspection ? ReturnTransaction::INSPECTION_PENDING : null,
                'received_by' => $user->id,
                'remarks' => $validated['remarks'] ?? null,
                'count_verified' => true,
            ]);

            if ($isMissing) {
                $borrowingItem->quantity_missing_on_return += $qty;
                $this->stockService->recordMovement(
                    item: $item,
                    movementType: StockMovement::TYPE_MISSING,
                    quantity: $qty,
                    actor: $user,
                    remarks: "Reported missing on return ({$returnTx->return_code} / {$borrowing->transaction_code}). " . ($validated['remarks'] ?? ''),
                    options: [
                        'from_borrowed' => true,
                        'reference' => $returnTx,
                        'mark_item_missing' => ($item->quantity - $qty) === 0,
                    ]
                );

                $this->notifications->sendToRoles(
                    [Role::SLUG_ADMIN],
                    'missing_on_return',
                    'Missing Equipment Reported on Return',
                    "{$qty} unit(s) of {$item->name} ({$item->property_code}) reported missing from {$borrowing->transaction_code}.",
                    'danger',
                    route('borrowings.show', $borrowing),
                    $returnTx
                );
            } elseif ($isDamaged) {
                // Flagged for inspection: do NOT increment available_quantity automatically
                $borrowingItem->quantity_damaged_on_return += $qty;
                $this->stockService->recordMovement(
                    item: $item,
                    movementType: StockMovement::TYPE_DAMAGED,
                    quantity: $qty,
                    actor: $user,
                    remarks: "Returned damaged — flagged for inspection ({$returnTx->return_code} / {$borrowing->transaction_code}). " . ($validated['remarks'] ?? ''),
                    options: [
                        'from_borrowed' => true,
                        'reference' => $returnTx,
                    ]
                );

                $item->update([
                    'condition_id' => $condition->id,
                    'updated_by' => $user->id,
                ]);

                $this->notifications->sendToRoles(
                    [Role::SLUG_ADMIN],
                    'damaged_return',
                    'Damaged Equipment Returned — Inspection Required',
                    "{$qty} unit(s) of {$item->name} ({$item->property_code}) from {$borrowing->transaction_code} were returned damaged and held for inspection.",
                    'warning',
                    route('borrowings.show', $borrowing),
                    $returnTx
                );
            } else {
                $this->stockService->recordMovement(
                    item: $item,
                    movementType: StockMovement::TYPE_RETURNING,
                    quantity: $qty,
                    actor: $user,
                    remarks: "Returned in {$condition->name} condition ({$returnTx->return_code} / {$borrowing->transaction_code}). " . ($validated['remarks'] ?? ''),
                    options: [
                        'reference' => $returnTx,
                    ]
                );

                $item->update([
                    'condition_id' => $condition->id,
                    'updated_by' => $user->id,
                ]);
            }

            $borrowingItem->quantity_returned += $qty;
            $borrowingItem->status = $borrowingItem->remainingQuantity() === 0
                ? 'returned'
                : 'partially_returned';
            $borrowingItem->save();

            // Refresh all items on parent request to update overall status
            $borrowing->load('items');
            $allReturned = $borrowing->items->every(fn (BorrowingItem $bi) => $bi->remainingQuantity() === 0);

            if ($allReturned) {
                $borrowing->status = BorrowingRequest::STATUS_RETURNED;
                $borrowing->actual_return_date = $validated['actual_return_date'];
            } else {
                $isPastDue = $borrowing->expected_return_date
                    && $borrowing->expected_return_date->copy()->endOfDay()->lt(Carbon::today()->endOfDay());
                $borrowing->status = $isPastDue
                    ? BorrowingRequest::STATUS_OVERDUE
                    : BorrowingRequest::STATUS_PARTIALLY_RETURNED;
            }
            $borrowing->save();

            $this->notifications->sendToUser(
                $borrowing->requester_user_id,
                'borrowing_returned',
                'Equipment Return Recorded',
                "Return {$returnTx->return_code} for {$borrowing->transaction_code} ({$qty} x {$item->name}) has been processed.",
                $flaggedForInspection ? 'warning' : 'success',
                route('borrowings.show', $borrowing),
                $returnTx
            );

            $this->auditLog->log(
                action: 'borrowing.return_processed',
                module: 'borrowings',
                auditable: $returnTx,
                newValues: [
                    'return_code' => $returnTx->return_code,
                    'quantity_returned' => $qty,
                    'is_damaged' => $isDamaged,
                    'is_missing' => $isMissing,
                    'flagged_for_inspection' => $flaggedForInspection,
                ],
                description: "Processed return {$returnTx->return_code} for {$borrowing->transaction_code}.",
                recordLabel: $returnTx->return_code,
                actor: $user
            );
        });

        return back()->with('success', 'Return transaction recorded successfully.');
    }

    public function resolveInspection(Request $request, ReturnTransaction $returnTransaction): RedirectResponse
    {
        if (! $returnTransaction->flagged_for_inspection || $returnTransaction->inspection_status !== ReturnTransaction::INSPECTION_PENDING) {
            return back()->with('error', 'This return record is not awaiting inspection.');
        }

        $validated = $request->validate([
            'resolution' => ['required', Rule::in([
                ReturnTransaction::INSPECTION_CLEARED,
                ReturnTransaction::INSPECTION_DAMAGED,
                ReturnTransaction::INSPECTION_MAINTENANCE,
                ReturnTransaction::INSPECTION_DISPOSED,
            ])],
            'condition_id' => ['nullable', 'exists:inventory_conditions,id'],
            'inspection_remarks' => ['required', 'string', 'min:3'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($returnTransaction, $validated, $user): void {
            $item = InventoryItem::lockForUpdate()->findOrFail($returnTransaction->inventory_item_id);
            $qty = $returnTransaction->quantity_returned;
            $resolution = $validated['resolution'];

            if ($resolution === ReturnTransaction::INSPECTION_CLEARED) {
                $this->stockService->recordMovement(
                    item: $item,
                    movementType: StockMovement::TYPE_RETURNING,
                    quantity: $qty,
                    actor: $user,
                    remarks: "Inspection cleared ({$returnTransaction->return_code}): {$validated['inspection_remarks']}",
                    options: ['reference' => $returnTransaction]
                );
            } elseif ($resolution === ReturnTransaction::INSPECTION_DAMAGED) {
                $item->status = InventoryItem::STATUS_DAMAGED;
            } elseif ($resolution === ReturnTransaction::INSPECTION_MAINTENANCE) {
                $item->status = InventoryItem::STATUS_UNDER_MAINTENANCE;
            } elseif ($resolution === ReturnTransaction::INSPECTION_DISPOSED) {
                $this->stockService->recordMovement(
                    item: $item,
                    movementType: StockMovement::TYPE_DISPOSAL,
                    quantity: $qty,
                    actor: $user,
                    remarks: "Disposed after return inspection ({$returnTransaction->return_code}): {$validated['inspection_remarks']}",
                    options: [
                        'from_borrowed' => true,
                        'reference' => $returnTransaction,
                    ]
                );
            }

            if (! empty($validated['condition_id'])) {
                $item->condition_id = (int) $validated['condition_id'];
            }
            $item->updated_by = $user->id;
            $item->save();

            $returnTransaction->update([
                'inspection_status' => $resolution,
                'inspected_by' => $user->id,
                'inspected_at' => now(),
                'inspection_remarks' => $validated['inspection_remarks'],
            ]);

            $this->auditLog->log(
                action: 'return.inspection_resolved',
                module: 'borrowings',
                auditable: $returnTransaction,
                oldValues: ['inspection_status' => ReturnTransaction::INSPECTION_PENDING],
                newValues: ['inspection_status' => $resolution, 'inspection_remarks' => $validated['inspection_remarks']],
                description: "Inspection resolved for {$returnTransaction->return_code} as {$resolution}.",
                recordLabel: $returnTransaction->return_code,
                actor: $user
            );
        });

        return back()->with('success', "Inspection for {$returnTransaction->return_code} resolved.");
    }
}
