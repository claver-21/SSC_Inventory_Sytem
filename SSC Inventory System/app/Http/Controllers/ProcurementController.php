<?php

namespace App\Http\Controllers;

use App\Models\Acquisition;
use App\Models\InventoryCategory;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Services\InventoryStockService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProcurementController extends Controller
{
    public function __construct(
        protected InventoryStockService $stockService,
        protected AuditLogService $auditLog,
        protected SettingsService $settings
    ) {
    }

    public function index(Request $request): View
    {
        $query = Acquisition::query()->with(['supplier', 'category', 'inventoryItem', 'recorder']);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('purchase_reference', 'like', "%{$search}%")
                    ->orWhere('item_name', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('funding_source', 'like', "%{$search}%");
            });
        }

        if ($supplierId = $request->input('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($fundingSource = $request->input('funding_source')) {
            $query->where('funding_source', $fundingSource);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('purchase_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('purchase_date', '<=', $dateTo);
        }

        $totalAcquisitions = Acquisition::count();
        $totalSpend = (float) Acquisition::sum('total_cost');
        $monthSpend = (float) Acquisition::whereYear('purchase_date', now()->year)
            ->whereMonth('purchase_date', now()->month)
            ->sum('total_cost');
        $totalUnitsAcquired = (int) Acquisition::sum('quantity_acquired');

        $fundingSummary = Acquisition::query()
            ->select('funding_source', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_cost) as total'))
            ->groupBy('funding_source')
            ->orderByDesc('total')
            ->get();

        return view('procurement.index', [
            'acquisitions' => $query->latest('purchase_date')->latest('id')->paginate(15)->withQueryString(),
            'suppliers' => Supplier::withCount('acquisitions')->orderBy('name')->get(),
            'fundingSources' => Acquisition::distinct()->orderBy('funding_source')->pluck('funding_source'),
            'fundingSummary' => $fundingSummary,
            'summary' => [
                'count' => $totalAcquisitions,
                'total_spend' => $totalSpend,
                'month_spend' => $monthSpend,
                'total_units' => $totalUnitsAcquired,
            ],
            'activeTab' => $request->input('tab', 'acquisitions'),
        ]);
    }

    public function create(): View
    {
        $year = now()->format('Y');
        $count = Acquisition::withTrashed()->where('purchase_reference', 'like', "PO-{$year}-%")->count() + 1;
        do {
            $suggestedRef = sprintf('PO-%s-%04d', $year, $count);
            $count++;
        } while (Acquisition::withTrashed()->where('purchase_reference', $suggestedRef)->exists());

        return view('procurement.create', [
            'suggestedRef' => $suggestedRef,
            'items' => InventoryItem::orderBy('name')->get(['id', 'property_code', 'name', 'category_id']),
            'categories' => InventoryCategory::where('is_active', true)->orderBy('name')->get(),
            'locations' => InventoryLocation::where('is_active', true)->orderBy('name')->get(),
            'conditions' => InventoryCondition::orderBy('severity_rank')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_reference' => ['required', 'string', 'max:60', Rule::unique('acquisitions', 'purchase_reference')],
            'inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
            'item_name' => ['required', 'string', 'max:180'],
            'category_id' => ['nullable', 'exists:inventory_categories,id'],
            'quantity_acquired' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'purchase_date' => ['required', 'date'],
            'funding_source' => ['required', 'string', 'max:150'],
            'remarks' => ['nullable', 'string'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'sync_mode' => ['nullable', Rule::in(['none', 'existing', 'new_item'])],
            'new_property_code' => ['nullable', 'required_if:sync_mode,new_item', 'string', 'max:60', Rule::unique('inventory_items', 'property_code')],
            'new_location_id' => ['nullable', 'exists:inventory_locations,id'],
            'new_condition_id' => ['nullable', 'exists:inventory_conditions,id'],
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('private/receipts', 'local');
        }

        $supplierName = $validated['supplier_name'] ?? null;
        if (! $supplierName && ! empty($validated['supplier_id'])) {
            $supplierName = Supplier::find($validated['supplier_id'])?->name;
        }

        $qty = (int) $validated['quantity_acquired'];
        $unitCost = round((float) $validated['unit_cost'], 2);
        $totalCost = round($qty * $unitCost, 2);
        $user = $request->user();
        $syncMode = $validated['sync_mode'] ?? (! empty($validated['inventory_item_id']) ? 'existing' : 'none');

        $acquisition = DB::transaction(function () use ($validated, $receiptPath, $supplierName, $qty, $unitCost, $totalCost, $user, $syncMode): Acquisition {
            $linkedItemId = $validated['inventory_item_id'] ?? null;
            $synced = false;

            if ($syncMode === 'new_item') {
                $catId = $validated['category_id'] ?? InventoryCategory::value('id');
                $condId = $validated['new_condition_id'] ?? InventoryCondition::where('slug', 'excellent')->value('id') ?? InventoryCondition::value('id');

                $newItem = InventoryItem::create([
                    'property_code' => strtoupper(trim($validated['new_property_code'])),
                    'name' => $validated['item_name'],
                    'category_id' => $catId,
                    'quantity' => 0,
                    'available_quantity' => 0,
                    'minimum_stock_level' => (int) $this->settings->get('default_low_stock_threshold', 2),
                    'unit_of_measurement' => 'piece',
                    'acquisition_date' => $validated['purchase_date'],
                    'acquisition_cost' => $unitCost,
                    'supplier_id' => $validated['supplier_id'] ?? null,
                    'supplier_name' => $supplierName,
                    'status' => InventoryItem::STATUS_AVAILABLE,
                    'condition_id' => $condId,
                    'location_id' => $validated['new_location_id'] ?? null,
                    'responsible_user_id' => $user->id,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
                $linkedItemId = $newItem->id;
            }

            $acq = Acquisition::create([
                'purchase_reference' => strtoupper(trim($validated['purchase_reference'])),
                'inventory_item_id' => $linkedItemId,
                'item_name' => $validated['item_name'],
                'category_id' => $validated['category_id'] ?? null,
                'quantity_acquired' => $qty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'supplier_name' => $supplierName,
                'purchase_date' => $validated['purchase_date'],
                'funding_source' => $validated['funding_source'],
                'receipt_path' => $receiptPath,
                'remarks' => $validated['remarks'] ?? null,
                'recorded_by' => $user->id,
                'synced_to_inventory' => false,
            ]);

            if ($linkedItemId && in_array($syncMode, ['existing', 'new_item'], true)) {
                $this->stockService->recordMovement(
                    item: $linkedItemId,
                    movementType: StockMovement::TYPE_STOCK_IN,
                    quantity: $qty,
                    actor: $user,
                    remarks: "Procurement {$acq->purchase_reference} ({$acq->funding_source}).",
                    options: ['reference' => $acq]
                );
                $synced = true;
                $acq->update(['synced_to_inventory' => true]);
            }

            $this->auditLog->log(
                action: 'procurement.recorded',
                module: 'procurement',
                auditable: $acq,
                newValues: [
                    'purchase_reference' => $acq->purchase_reference,
                    'item_name' => $acq->item_name,
                    'quantity_acquired' => $qty,
                    'total_cost' => $totalCost,
                    'synced_to_inventory' => $synced,
                ],
                description: "Recorded acquisition {$acq->purchase_reference}: {$qty} x {$acq->item_name} (₱" . number_format($totalCost, 2) . ").",
                recordLabel: $acq->purchase_reference,
                actor: $user
            );

            return $acq;
        });

        return redirect()
            ->route('procurement.index')
            ->with('success', "Acquisition {$acquisition->purchase_reference} recorded cleanly.");
    }

    public function downloadReceipt(Acquisition $acquisition): BinaryFileResponse
    {
        if (! $acquisition->receipt_path || ! Storage::disk('local')->exists($acquisition->receipt_path)) {
            abort(404, 'Supporting document not found.');
        }

        return response()->file(Storage::disk('local')->path($acquisition->receipt_path));
    }
}
