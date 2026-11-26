<?php

namespace App\Http\Controllers;

use App\Models\IncidentReport;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\Role;
use App\Models\StockMovement;
use App\Services\AuditLogService;
use App\Services\InventoryStockService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidentReportController extends Controller
{
    public function __construct(
        protected InventoryStockService $stockService,
        protected NotificationService $notifications,
        protected AuditLogService $auditLog
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $canResolve = $user->hasPermission('incidents.resolve');

        $query = IncidentReport::query()
            ->with(['inventoryItem', 'reporter', 'resolver']);

        if (! $canResolve) {
            $query->where('reported_by', $user->id);
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return view('incidents.index', [
            'incidents' => $query->latest()->paginate(15)->withQueryString(),
            'items' => InventoryItem::orderBy('name')->get(['id', 'property_code', 'name']),
            'types' => IncidentReport::TYPES,
            'statuses' => IncidentReport::STATUSES,
            'canResolve' => $canResolve,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', Rule::in([IncidentReport::CATEGORY_EQUIPMENT, IncidentReport::CATEGORY_SECURITY])],
            'inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
            'incident_type' => ['required', Rule::in(array_keys(IncidentReport::TYPES))],
            'affected_quantity' => ['nullable', 'integer', 'min:1'],
            'description' => ['required', 'string', 'min:5'],
        ]);

        $category = $validated['category'] ?? (
            in_array($validated['incident_type'], [IncidentReport::TYPE_DAMAGED, IncidentReport::TYPE_MISSING], true)
                ? IncidentReport::CATEGORY_EQUIPMENT
                : IncidentReport::CATEGORY_SECURITY
        );

        $item = ! empty($validated['inventory_item_id'])
            ? InventoryItem::findOrFail($validated['inventory_item_id'])
            : null;

        if ($category === IncidentReport::CATEGORY_EQUIPMENT && ! $item) {
            return back()->withErrors([
                'inventory_item_id' => 'Please select the affected inventory item for equipment incidents.',
            ]);
        }

        $affectedQty = (int) ($validated['affected_quantity'] ?? 1);
        $user = $request->user();

        if ($item && $affectedQty > max(1, $item->quantity)) {
            return back()->withErrors([
                'affected_quantity' => "Affected quantity cannot exceed total item quantity ({$item->quantity}).",
            ]);
        }

        $report = IncidentReport::create([
            'report_code' => 'INC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
            'inventory_item_id' => $item?->id,
            'reported_by' => $user->id,
            'category' => $category,
            'incident_type' => $validated['incident_type'],
            'affected_quantity' => $affectedQty,
            'description' => $validated['description'],
            'status' => IncidentReport::STATUS_REPORTED,
        ]);

        $typeLabel = IncidentReport::TYPES[$validated['incident_type']] ?? ucfirst($validated['incident_type']);
        $targetRoles = [Role::SLUG_ADMIN];

        $this->notifications->sendToRoles(
            $targetRoles,
            'incident_' . $validated['incident_type'],
            "{$typeLabel} Reported",
            $item
                ? "{$user->name} reported {$affectedQty} unit(s) of {$item->name} ({$item->property_code}): {$typeLabel}."
                : "{$user->name} reported a security incident ({$report->report_code}): {$typeLabel}.",
            in_array($validated['incident_type'], [IncidentReport::TYPE_MISSING, IncidentReport::TYPE_UNAUTHORIZED_ACCESS, IncidentReport::TYPE_PHISHING], true) ? 'danger' : 'warning',
            route('incidents.index'),
            $report,
            $user->id
        );

        $this->auditLog->log(
            action: 'INCIDENT_REPORTED',
            module: 'incidents',
            auditable: $report,
            newValues: $report->only(['report_code', 'category', 'incident_type', 'affected_quantity', 'status']),
            description: "{$typeLabel} incident {$report->report_code} reported" . ($item ? " for {$item->property_code}." : '.'),
            recordLabel: $report->report_code,
            actor: $user
        );

        return back()->with('success', "Incident report {$report->report_code} submitted cleanly.");
    }

    public function resolve(Request $request, IncidentReport $incident): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(IncidentReport::STATUSES))],
            'resolution_notes' => ['required', 'string', 'min:3'],
            'lessons_learned' => ['nullable', 'string'],
            'apply_stock_movement' => ['sometimes', 'boolean'],
            'new_condition_id' => ['nullable', 'exists:inventory_conditions,id'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($request, $incident, $validated, $user): void {
            $item = $incident->inventoryItem;
            $isResolvedStage = in_array($validated['status'], [IncidentReport::STATUS_RESOLVED, IncidentReport::STATUS_LESSONS_LEARNED], true);

            if ($request->boolean('apply_stock_movement') && $item && ! in_array($incident->status, [IncidentReport::STATUS_RESOLVED, IncidentReport::STATUS_LESSONS_LEARNED], true)) {
                $movementType = $incident->incident_type === IncidentReport::TYPE_MISSING
                    ? StockMovement::TYPE_MISSING
                    : StockMovement::TYPE_DAMAGED;

                $this->stockService->recordMovement(
                    item: $item,
                    movementType: $movementType,
                    quantity: $incident->affected_quantity,
                    actor: $user,
                    remarks: "Incident {$incident->report_code}: {$validated['resolution_notes']}",
                    options: [
                        'reference' => $incident,
                        'mark_item_damaged' => $movementType === StockMovement::TYPE_DAMAGED,
                        'mark_item_missing' => $movementType === StockMovement::TYPE_MISSING,
                    ]
                );
            }

            if (! empty($validated['new_condition_id']) && $item) {
                $item->update([
                    'condition_id' => $validated['new_condition_id'],
                    'updated_by' => $user->id,
                ]);
            }

            $oldStatus = $incident->status;
            $incident->update([
                'status' => $validated['status'],
                'resolved_by' => $isResolvedStage ? $user->id : $incident->resolved_by,
                'resolved_at' => $isResolvedStage ? ($incident->resolved_at ?? now()) : null,
                'resolution_notes' => $validated['resolution_notes'],
                'lessons_learned' => $validated['lessons_learned'] ?? $incident->lessons_learned,
            ]);

            $this->notifications->sendToUser(
                $incident->reported_by,
                'incident_resolved',
                'Incident Report Updated',
                "Your report {$incident->report_code} is now marked as " . (IncidentReport::STATUSES[$validated['status']] ?? $validated['status']) . '.',
                'info',
                route('incidents.index'),
                $incident
            );

            $this->auditLog->log(
                action: 'INCIDENT_UPDATED',
                module: 'incidents',
                auditable: $incident,
                oldValues: ['status' => $oldStatus],
                newValues: $incident->only(['status', 'resolution_notes', 'lessons_learned']),
                description: "Incident {$incident->report_code} updated to {$validated['status']}.",
                recordLabel: $incident->report_code,
                actor: $user
            );
        });

        return back()->with('success', "Incident report {$incident->report_code} updated.");
    }
}
