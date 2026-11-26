<?php

namespace App\Http\Controllers;

use App\Models\InventoryAudit;
use App\Models\InventoryAuditItem;
use App\Models\InventoryCategory;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Role;
use App\Models\StockMovement;
use App\Services\AuditLogService;
use App\Services\InventoryStockService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryAuditController extends Controller
{
    public function __construct(
        protected InventoryStockService $stockService,
        protected NotificationService $notifications,
        protected AuditLogService $auditLog
    ) {
    }

    public function index(Request $request): View
    {
        $query = InventoryAudit::query()
            ->with(['location', 'category', 'conductor', 'approver'])
            ->withCount('items')
            ->withCount(['items as discrepancy_items_count' => fn ($q) => $q->where('discrepancy', '!=', 0)]);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return view('audits.index', [
            'audits' => $query->latest('audit_date')->latest('id')->paginate(15)->withQueryString(),
            'statuses' => InventoryAudit::STATUSES,
        ]);
    }

    public function create(): View
    {
        $year = now()->format('Y');
        $count = InventoryAudit::where('audit_code', 'like', "AUD-{$year}-%")->count() + 1;
        do {
            $suggestedCode = sprintf('AUD-%s-%04d', $year, $count);
            $count++;
        } while (InventoryAudit::where('audit_code', $suggestedCode)->exists());

        return view('audits.create', [
            'suggestedCode' => $suggestedCode,
            'locations' => InventoryLocation::where('is_active', true)->orderBy('name')->get(),
            'categories' => InventoryCategory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'audit_code' => ['required', 'string', 'max:40', Rule::unique('inventory_audits', 'audit_code')],
            'title' => ['required', 'string', 'max:180'],
            'audit_date' => ['required', 'date'],
            'location_id' => ['nullable', 'exists:inventory_locations,id'],
            'category_id' => ['nullable', 'exists:inventory_categories,id'],
            'remarks' => ['nullable', 'string'],
        ]);

        $itemsQuery = InventoryItem::query()
            ->when(! empty($validated['location_id']), fn ($q) => $q->where('location_id', $validated['location_id']))
            ->when(! empty($validated['category_id']), fn ($q) => $q->where('category_id', $validated['category_id']))
            ->orderBy('property_code');

        $items = $itemsQuery->get();

        if ($items->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors(['title' => 'No active inventory items match the selected location/category filter for this audit session.']);
        }

        $user = $request->user();

        $audit = DB::transaction(function () use ($validated, $items, $user): InventoryAudit {
            $created = InventoryAudit::create([
                'audit_code' => strtoupper(trim($validated['audit_code'])),
                'title' => $validated['title'],
                'audit_date' => $validated['audit_date'],
                'location_id' => $validated['location_id'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'status' => InventoryAudit::STATUS_IN_PROGRESS,
                'conducted_by' => $user->id,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            foreach ($items as $item) {
                InventoryAuditItem::create([
                    'inventory_audit_id' => $created->id,
                    'inventory_item_id' => $item->id,
                    'system_quantity' => $item->quantity,
                    'physical_quantity' => $item->quantity,
                    'discrepancy' => 0,
                    'damaged_quantity' => 0,
                    'missing_quantity' => 0,
                    'observed_condition_id' => $item->condition_id,
                    'adjustment_applied' => false,
                ]);
            }

            $this->auditLog->log(
                action: 'audit.created',
                module: 'audits',
                auditable: $created,
                newValues: ['audit_code' => $created->audit_code, 'item_count' => $items->count()],
                description: "Created physical inventory audit {$created->audit_code} ({$items->count()} items snapshotted).",
                recordLabel: $created->audit_code,
                actor: $user
            );

            return $created;
        });

        return redirect()
            ->route('audits.show', $audit)
            ->with('success', "Audit session {$audit->audit_code} initialized with {$items->count()} item(s).");
    }

    public function show(InventoryAudit $audit): View
    {
        $audit->load([
            'location',
            'category',
            'conductor',
            'approver',
            'items.inventoryItem.category',
            'items.inventoryItem.location',
            'items.inventoryItem.condition',
            'items.observedCondition',
        ]);

        return view('audits.show', [
            'audit' => $audit,
            'conditions' => InventoryCondition::orderBy('severity_rank')->get(),
        ]);
    }

    public function updateCounts(Request $request, InventoryAudit $audit): RedirectResponse
    {
        if (! $audit->isEditable()) {
            return back()->with('error', 'Audit counts are locked and can no longer be modified.');
        }

        $validated = $request->validate([
            'action_type' => ['required', Rule::in(['save_draft', 'submit_approval'])],
            'remarks' => ['nullable', 'string'],
            'counts' => ['required', 'array'],
            'counts.*.physical_quantity' => ['required', 'integer', 'min:0'],
            'counts.*.damaged_quantity' => ['nullable', 'integer', 'min:0'],
            'counts.*.missing_quantity' => ['nullable', 'integer', 'min:0'],
            'counts.*.observed_condition_id' => ['nullable', 'exists:inventory_conditions,id'],
            'counts.*.remarks' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($audit, $validated, $user): void {
            $audit->load('items');

            foreach ($audit->items as $auditItem) {
                $row = $validated['counts'][$auditItem->id] ?? null;
                if (! $row) {
                    continue;
                }

                $physical = (int) $row['physical_quantity'];
                $discrepancy = $physical - (int) $auditItem->system_quantity;

                $auditItem->update([
                    'physical_quantity' => $physical,
                    'discrepancy' => $discrepancy,
                    'damaged_quantity' => (int) ($row['damaged_quantity'] ?? 0),
                    'missing_quantity' => (int) ($row['missing_quantity'] ?? 0),
                    'observed_condition_id' => $row['observed_condition_id'] ?: $auditItem->observed_condition_id,
                    'remarks' => $row['remarks'] ?? null,
                ]);
            }

            $newStatus = $validated['action_type'] === 'submit_approval'
                ? InventoryAudit::STATUS_PENDING_APPROVAL
                : InventoryAudit::STATUS_IN_PROGRESS;

            $audit->update([
                'status' => $newStatus,
                'remarks' => $validated['remarks'] ?? $audit->remarks,
            ]);

            if ($newStatus === InventoryAudit::STATUS_PENDING_APPROVAL) {
                $this->notifications->sendToRoles(
                    [Role::SLUG_ADMIN],
                    'audit_pending_approval',
                    'Inventory Audit Ready for Approval',
                    "{$user->name} submitted physical audit {$audit->audit_code} for approval.",
                    'info',
                    route('audits.show', $audit),
                    $audit,
                    $user->id
                );
            }

            $this->auditLog->log(
                action: $newStatus === InventoryAudit::STATUS_PENDING_APPROVAL ? 'audit.submitted' : 'audit.updated',
                module: 'audits',
                auditable: $audit,
                newValues: ['status' => $newStatus],
                description: ($newStatus === InventoryAudit::STATUS_PENDING_APPROVAL ? 'Submitted' : 'Saved') . " physical audit {$audit->audit_code}.",
                recordLabel: $audit->audit_code,
                actor: $user
            );
        });

        $msg = $validated['action_type'] === 'submit_approval'
            ? "Audit {$audit->audit_code} submitted for Administrator approval."
            : "Audit counts saved for {$audit->audit_code}.";

        return redirect()->route('audits.show', $audit)->with('success', $msg);
    }

    public function approve(Request $request, InventoryAudit $audit): RedirectResponse
    {
        if ($audit->status !== InventoryAudit::STATUS_PENDING_APPROVAL) {
            return back()->with('error', 'Only audits pending approval can be approved.');
        }

        $validated = $request->validate([
            'approval_remarks' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($audit, $validated, $user): void {
            $audit->load('items.inventoryItem');

            foreach ($audit->items as $auditItem) {
                $item = $auditItem->inventoryItem;
                if (! $item) {
                    continue;
                }

                // Preserve original audit snapshot & physical count; apply stock reconciliation via StockMovement
                if ($auditItem->discrepancy !== 0 && ! $auditItem->adjustment_applied) {
                    $this->stockService->recordMovement(
                        item: $item,
                        movementType: StockMovement::TYPE_ADJUSTMENT,
                        quantity: $auditItem->discrepancy,
                        actor: $user,
                        remarks: "Physical Audit {$audit->audit_code} reconciliation (System: {$auditItem->system_quantity}, Physical: {$auditItem->physical_quantity}).",
                        options: ['reference' => $audit]
                    );

                    $auditItem->update(['adjustment_applied' => true]);
                }

                if ($auditItem->observed_condition_id && $item->condition_id !== $auditItem->observed_condition_id) {
                    $item->update([
                        'condition_id' => $auditItem->observed_condition_id,
                        'updated_by' => $user->id,
                    ]);
                }
            }

            $audit->update([
                'status' => InventoryAudit::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_remarks' => $validated['approval_remarks'] ?? 'Approved and reconciled.',
            ]);

            $this->notifications->sendToUser(
                $audit->conducted_by,
                'audit_approved',
                'Inventory Audit Approved',
                "Physical inventory audit {$audit->audit_code} has been approved and stock discrepancies reconciled.",
                'success',
                route('audits.show', $audit),
                $audit
            );

            $this->auditLog->log(
                action: 'audit.approved',
                module: 'audits',
                auditable: $audit,
                oldValues: ['status' => InventoryAudit::STATUS_PENDING_APPROVAL],
                newValues: ['status' => InventoryAudit::STATUS_APPROVED],
                description: "Approved physical inventory audit {$audit->audit_code} and reconciled stock.",
                recordLabel: $audit->audit_code,
                actor: $user
            );
        });

        return redirect()
            ->route('audits.show', $audit)
            ->with('success', "Audit {$audit->audit_code} approved and inventory quantities reconciled.");
    }

    public function reject(Request $request, InventoryAudit $audit): RedirectResponse
    {
        if ($audit->status !== InventoryAudit::STATUS_PENDING_APPROVAL) {
            return back()->with('error', 'Only audits pending approval can be rejected.');
        }

        $validated = $request->validate([
            'approval_remarks' => ['required', 'string', 'min:3'],
        ]);

        $user = $request->user();

        $audit->update([
            'status' => InventoryAudit::STATUS_REJECTED,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'approval_remarks' => $validated['approval_remarks'],
        ]);

        $this->notifications->sendToUser(
            $audit->conducted_by,
            'audit_rejected',
            'Inventory Audit Rejected',
            "Physical inventory audit {$audit->audit_code} was rejected: {$validated['approval_remarks']}",
            'danger',
            route('audits.show', $audit),
            $audit
        );

        $this->auditLog->log(
            action: 'audit.rejected',
            module: 'audits',
            auditable: $audit,
            oldValues: ['status' => InventoryAudit::STATUS_PENDING_APPROVAL],
            newValues: ['status' => InventoryAudit::STATUS_REJECTED, 'approval_remarks' => $validated['approval_remarks']],
            description: "Rejected physical inventory audit {$audit->audit_code}.",
            recordLabel: $audit->audit_code,
            actor: $user
        );

        return redirect()
            ->route('audits.show', $audit)
            ->with('success', "Audit {$audit->audit_code} rejected.");
    }
}
