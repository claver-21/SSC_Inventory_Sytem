<?php

namespace App\Http\Controllers;

use App\Models\BorrowingItem;
use App\Models\BorrowingRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\InventoryStockService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryStockService $stockService,
        protected AuditLogService $auditLog,
        protected SettingsService $settings
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $showArchived = $request->boolean('archived') && $user->hasPermission('inventory.archive');

        $query = InventoryItem::query()
            ->with(['category', 'condition', 'location', 'responsibleUser']);

        if ($showArchived) {
            $query->onlyTrashed();
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('property_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('brand_model', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($locationId = $request->input('location_id')) {
            $query->where('location_id', $locationId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($conditionId = $request->input('condition_id')) {
            $query->where('condition_id', $conditionId);
        }

        if ($responsibleId = $request->input('responsible_user_id')) {
            $query->where('responsible_user_id', $responsibleId);
        }

        if ($request->boolean('low_stock')) {
            $query->where('status', '!=', InventoryItem::STATUS_DISPOSED)
                ->whereColumn('available_quantity', '<=', 'minimum_stock_level');
        }

        $allowedSorts = ['property_code', 'name', 'quantity', 'available_quantity', 'acquisition_cost', 'updated_at'];
        $sortBy = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'updated_at';
        $sortDir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        $items = $query->orderBy($sortBy, $sortDir)
            ->paginate(15)
            ->withQueryString();

        return view('inventory.index', [
            'items' => $items,
            'categories' => InventoryCategory::where('is_active', true)->orderBy('name')->get(),
            'locations' => InventoryLocation::where('is_active', true)->orderBy('name')->get(),
            'conditions' => InventoryCondition::orderBy('severity_rank')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => InventoryItem::STATUSES,
            'showArchived' => $showArchived,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    public function create(Request $request): View
    {
        $categories = InventoryCategory::where('is_active', true)->orderBy('name')->get();
        $defaultCategory = $categories->first();

        return view('inventory.create', [
            'categories' => $categories,
            'locations' => InventoryLocation::where('is_active', true)->orderBy('name')->get(),
            'conditions' => InventoryCondition::orderBy('severity_rank')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'position']),
            'statuses' => InventoryItem::STATUSES,
            'suggestedCode' => $this->suggestPropertyCode($defaultCategory?->code ?? 'GEN'),
            'defaultMinStock' => (int) $this->settings->get('default_low_stock_threshold', 2),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'property_code' => ['required', 'string', 'max:60', Rule::unique('inventory_items', 'property_code')],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:inventory_categories,id'],
            'brand_model' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'sensitive_notes' => ['nullable', 'string', 'max:1000'],
            'quantity' => ['required', 'integer', 'min:0'],
            'minimum_stock_level' => ['required', 'integer', 'min:0'],
            'unit_of_measurement' => ['required', 'string', 'max:50'],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(array_keys(InventoryItem::STATUSES))],
            'condition_id' => ['nullable', 'exists:inventory_conditions,id'],
            'location_id' => ['nullable', 'exists:inventory_locations,id'],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'remarks' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('items', 'public');
        }

        $supplierName = $validated['supplier_name'] ?? null;
        if (! $supplierName && ! empty($validated['supplier_id'])) {
            $supplierName = Supplier::find($validated['supplier_id'])?->name;
        }

        $categoryId = $validated['category_id']
            ?? InventoryCategory::where('is_active', true)->orderBy('id')->value('id')
            ?? InventoryCategory::value('id');
        $conditionId = $validated['condition_id']
            ?? InventoryCondition::where('slug', 'good')->value('id')
            ?? InventoryCondition::value('id');

        $initialQuantity = (int) $validated['quantity'];
        $user = $request->user();

        $item = DB::transaction(function () use ($validated, $imagePath, $supplierName, $categoryId, $conditionId, $initialQuantity, $user): InventoryItem {
            $created = InventoryItem::create([
                'property_code' => strtoupper(trim($validated['property_code'])),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'category_id' => $categoryId,
                'brand_model' => $validated['brand_model'] ?? null,
                'serial_number' => $validated['serial_number'] ?? null,
                'sensitive_notes' => $validated['sensitive_notes'] ?? null,
                'quantity' => 0,
                'available_quantity' => 0,
                'minimum_stock_level' => $validated['minimum_stock_level'],
                'unit_of_measurement' => $validated['unit_of_measurement'],
                'acquisition_date' => $validated['acquisition_date'] ?? null,
                'acquisition_cost' => $validated['acquisition_cost'] ?? 0,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'supplier_name' => $supplierName,
                'status' => $validated['status'],
                'condition_id' => $conditionId,
                'location_id' => $validated['location_id'] ?? null,
                'responsible_user_id' => $validated['responsible_user_id'] ?? $user->id,
                'remarks' => $validated['remarks'] ?? null,
                'image_path' => $imagePath,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if ($initialQuantity > 0) {
                $this->stockService->recordMovement(
                    item: $created,
                    movementType: StockMovement::TYPE_STOCK_IN,
                    quantity: $initialQuantity,
                    actor: $user,
                    remarks: 'Initial inventory registration stock entry.'
                );
                $created->refresh();
            }

            $this->auditLog->log(
                action: 'inventory.created',
                module: 'inventory',
                auditable: $created,
                newValues: $created->only(['property_code', 'name', 'category_id', 'quantity', 'status', 'condition_id', 'location_id']),
                description: "Registered inventory item {$created->name} ({$created->property_code}) with {$initialQuantity} unit(s).",
                recordLabel: $created->property_code,
                actor: $user
            );

            return $created;
        });

        return redirect()
            ->route('inventory.index')
            ->with('success', "Inventory item {$item->property_code} has been registered.");
    }

    public function show(int $id): View
    {
        $item = InventoryItem::withTrashed()
            ->with([
                'category',
                'condition',
                'location',
                'supplier',
                'responsibleUser',
                'creator',
                'updater',
            ])
            ->findOrFail($id);

        $movements = $item->stockMovements()
            ->with(['processor', 'fromLocation', 'toLocation'])
            ->latest('transaction_date')
            ->limit(25)
            ->get();

        $borrowings = BorrowingItem::query()
            ->with(['borrowingRequest.requester'])
            ->where('inventory_item_id', $item->id)
            ->latest()
            ->limit(20)
            ->get();

        $incidents = $item->incidentReports()
            ->with(['reporter', 'resolver'])
            ->latest()
            ->get();

        return view('inventory.show', [
            'item' => $item,
            'movements' => $movements,
            'borrowings' => $borrowings,
            'incidents' => $incidents,
            'conditions' => InventoryCondition::orderBy('severity_rank')->get(),
            'statuses' => InventoryItem::STATUSES,
        ]);
    }

    public function edit(InventoryItem $inventory): View
    {
        return view('inventory.edit', [
            'item' => $inventory,
            'categories' => InventoryCategory::where('is_active', true)->orderBy('name')->get(),
            'locations' => InventoryLocation::where('is_active', true)->orderBy('name')->get(),
            'conditions' => InventoryCondition::orderBy('severity_rank')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'position']),
            'statuses' => InventoryItem::STATUSES,
        ]);
    }

    public function update(Request $request, InventoryItem $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'property_code' => ['required', 'string', 'max:60', Rule::unique('inventory_items', 'property_code')->ignore($inventory->id)],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:inventory_categories,id'],
            'brand_model' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'sensitive_notes' => ['nullable', 'string', 'max:1000'],
            'minimum_stock_level' => ['required', 'integer', 'min:0'],
            'unit_of_measurement' => ['required', 'string', 'max:50'],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(array_keys(InventoryItem::STATUSES))],
            'condition_id' => ['nullable', 'exists:inventory_conditions,id'],
            'location_id' => ['nullable', 'exists:inventory_locations,id'],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'remarks' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $oldValues = $inventory->only([
            'property_code', 'name', 'category_id', 'brand_model', 'serial_number',
            'minimum_stock_level', 'status', 'condition_id', 'location_id', 'responsible_user_id',
        ]);

        $imagePath = $inventory->image_path;
        if ($request->hasFile('image')) {
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('items', 'public');
        }

        $supplierId = $request->exists('supplier_id') ? ($validated['supplier_id'] ?? null) : $inventory->supplier_id;
        $supplierName = $request->exists('supplier_name') ? ($validated['supplier_name'] ?? null) : $inventory->supplier_name;
        if (! $supplierName && ! empty($supplierId) && ($request->exists('supplier_id') || $request->exists('supplier_name'))) {
            $supplierName = Supplier::find($supplierId)?->name;
        }

        $inventory->update([
            'property_code' => strtoupper(trim($validated['property_code'])),
            'name' => $validated['name'],
            'description' => $request->exists('description') ? ($validated['description'] ?? null) : $inventory->description,
            'category_id' => $request->filled('category_id') ? $validated['category_id'] : $inventory->category_id,
            'brand_model' => $request->exists('brand_model') ? ($validated['brand_model'] ?? null) : $inventory->brand_model,
            'serial_number' => $request->exists('serial_number') ? ($validated['serial_number'] ?? null) : $inventory->serial_number,
            'sensitive_notes' => $request->exists('sensitive_notes') ? ($validated['sensitive_notes'] ?? null) : $inventory->sensitive_notes,
            'minimum_stock_level' => $validated['minimum_stock_level'],
            'unit_of_measurement' => $validated['unit_of_measurement'],
            'acquisition_date' => $request->exists('acquisition_date') ? ($validated['acquisition_date'] ?? null) : $inventory->acquisition_date,
            'acquisition_cost' => $request->exists('acquisition_cost') ? ($validated['acquisition_cost'] ?? 0) : $inventory->acquisition_cost,
            'supplier_id' => $supplierId,
            'supplier_name' => $supplierName,
            'status' => $validated['status'],
            'condition_id' => $request->filled('condition_id') ? $validated['condition_id'] : $inventory->condition_id,
            'location_id' => $request->exists('location_id') ? ($validated['location_id'] ?? null) : $inventory->location_id,
            'responsible_user_id' => $request->exists('responsible_user_id') ? ($validated['responsible_user_id'] ?? null) : $inventory->responsible_user_id,
            'remarks' => $request->exists('remarks') ? ($validated['remarks'] ?? null) : $inventory->remarks,
            'image_path' => $imagePath,
            'updated_by' => $request->user()->id,
        ]);

        $newValues = $inventory->only(array_keys($oldValues));

        $this->auditLog->log(
            action: 'inventory.updated',
            module: 'inventory',
            auditable: $inventory,
            oldValues: $oldValues,
            newValues: $newValues,
            description: "Updated metadata/status for {$inventory->name} ({$inventory->property_code}).",
            recordLabel: $inventory->property_code,
            actor: $request->user()
        );

        return redirect()
            ->route('inventory.index')
            ->with('success', "Inventory item {$inventory->property_code} updated successfully.");
    }

    public function updateCondition(Request $request, InventoryItem $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'condition_id' => ['required', 'exists:inventory_conditions,id'],
            'status' => ['required', Rule::in(array_keys(InventoryItem::STATUSES))],
            'remarks' => ['nullable', 'string'],
        ]);

        $old = $inventory->only(['condition_id', 'status', 'remarks']);

        $inventory->update([
            'condition_id' => $validated['condition_id'],
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? $inventory->remarks,
            'updated_by' => $request->user()->id,
        ]);

        $this->auditLog->log(
            action: 'inventory.condition_updated',
            module: 'inventory',
            auditable: $inventory,
            oldValues: $old,
            newValues: $inventory->only(['condition_id', 'status', 'remarks']),
            description: "Updated physical condition/status for {$inventory->property_code}.",
            recordLabel: $inventory->property_code,
            actor: $request->user()
        );

        return back()->with('success', 'Item condition and availability status updated.');
    }

    public function archive(Request $request, InventoryItem $inventory): RedirectResponse
    {
        $hasActiveBorrowings = BorrowingItem::query()
            ->where('inventory_item_id', $inventory->id)
            ->whereRaw('quantity_approved > quantity_returned')
            ->whereHas('borrowingRequest', fn ($q) => $q->whereIn('status', [
                BorrowingRequest::STATUS_APPROVED,
                BorrowingRequest::STATUS_PARTIALLY_RETURNED,
                BorrowingRequest::STATUS_OVERDUE,
            ]))
            ->exists();

        if ($hasActiveBorrowings) {
            return back()->with('error', 'Cannot archive this item while units are actively borrowed.');
        }

        $inventory->updated_by = $request->user()->id;
        $inventory->save();
        $inventory->delete();

        $this->auditLog->log(
            action: 'inventory.archived',
            module: 'inventory',
            auditable: $inventory,
            description: "Archived inventory record {$inventory->name} ({$inventory->property_code}).",
            recordLabel: $inventory->property_code,
            actor: $request->user()
        );

        return redirect()
            ->route('inventory.index')
            ->with('success', "Inventory item {$inventory->property_code} has been archived.");
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $item = InventoryItem::onlyTrashed()->findOrFail($id);
        $item->restore();
        $item->update(['updated_by' => $request->user()->id]);

        $this->auditLog->log(
            action: 'inventory.restored',
            module: 'inventory',
            auditable: $item,
            description: "Restored archived inventory item {$item->name} ({$item->property_code}).",
            recordLabel: $item->property_code,
            actor: $request->user()
        );

        return redirect()
            ->route('inventory.show', $item)
            ->with('success', "Inventory item {$item->property_code} has been restored.");
    }

    public function bulkCreate(): View
    {
        return view('inventory.bulk', [
            'categories' => InventoryCategory::where('is_active', true)->orderBy('name')->get(),
            'locations' => InventoryLocation::where('is_active', true)->orderBy('name')->get(),
            'conditions' => InventoryCondition::orderBy('severity_rank')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => InventoryItem::STATUSES,
        ]);
    }

    public function bulkTemplate(): StreamedResponse
    {
        $headers = [
            'property_code',
            'name',
            'category_code',
            'brand_model',
            'serial_number',
            'quantity',
            'minimum_stock_level',
            'unit_of_measurement',
            'acquisition_date',
            'acquisition_cost',
            'supplier_name',
            'condition_slug',
            'status',
            'location_name',
            'remarks',
        ];

        $sample = [
            'PCB-SSC-OFF-0001',
            'Epson EcoTank L3210 Printer',
            'OFF',
            'Epson L3210',
            'SN-EPSON-001',
            '1',
            '1',
            'unit',
            now()->format('Y-m-d'),
            '9800.00',
            'Botolan Tech Depot',
            'excellent',
            'available',
            'SSC Main Office',
            'Sample format row - replace before importing',
        ];

        return response()->streamDownload(function () use ($headers, $sample): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, $sample);
            fclose($out);
        }, 'pcb_ssc_bulk_inventory_template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $rows = [];

        if ($request->hasFile('csv_file')) {
            $request->validate([
                'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            ]);

            $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
            $header = null;
            while (($data = fgetcsv($handle)) !== false) {
                if (! $header) {
                    $header = array_map(fn ($h) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $h)), $data);
                    continue;
                }
                if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }
                $rows[] = array_combine($header, array_pad($data, count($header), null));
            }
            fclose($handle);
        } else {
            $rows = $request->input('items', []);
        }

        if (empty($rows)) {
            return back()->withErrors(['items' => 'Please provide at least one inventory item row or upload a CSV file.']);
        }

        $categoriesByCode = InventoryCategory::pluck('id', 'code');
        $conditionsBySlug = InventoryCondition::pluck('id', 'slug');
        $locationsByName = InventoryLocation::pluck('id', 'name');
        $defaultConditionId = $conditionsBySlug['good'] ?? InventoryCondition::value('id');
        $user = $request->user();

        $normalized = [];
        $codesInBatch = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            $code = strtoupper(trim((string) ($row['property_code'] ?? '')));

            $categoryId = $row['category_id'] ?? ($categoriesByCode[strtoupper(trim((string) ($row['category_code'] ?? '')))] ?? null);
            $conditionId = $row['condition_id'] ?? ($conditionsBySlug[strtolower(trim((string) ($row['condition_slug'] ?? 'good')))] ?? $defaultConditionId);
            $locationId = $row['location_id'] ?? ($locationsByName[trim((string) ($row['location_name'] ?? ''))] ?? null);

            $candidate = [
                'property_code' => $code,
                'name' => trim((string) ($row['name'] ?? '')),
                'category_id' => $categoryId,
                'brand_model' => $row['brand_model'] ?? null,
                'serial_number' => $row['serial_number'] ?? null,
                'quantity' => $row['quantity'] ?? 0,
                'minimum_stock_level' => $row['minimum_stock_level'] ?? 2,
                'unit_of_measurement' => trim((string) ($row['unit_of_measurement'] ?? 'piece')) ?: 'piece',
                'acquisition_date' => ! empty($row['acquisition_date']) ? $row['acquisition_date'] : null,
                'acquisition_cost' => ! empty($row['acquisition_cost']) ? $row['acquisition_cost'] : 0,
                'supplier_name' => $row['supplier_name'] ?? null,
                'status' => strtolower(trim((string) ($row['status'] ?? 'available'))) ?: 'available',
                'condition_id' => $conditionId,
                'location_id' => $locationId,
                'remarks' => $row['remarks'] ?? null,
            ];

            if (in_array($code, $codesInBatch, true)) {
                return back()->withInput()->withErrors(['items' => "Row {$rowNum}: Duplicate property code '{$code}' inside batch."]);
            }
            $codesInBatch[] = $code;

            $validator = Validator::make($candidate, [
                'property_code' => ['required', 'string', 'max:60', Rule::unique('inventory_items', 'property_code')],
                'name' => ['required', 'string', 'max:180'],
                'category_id' => ['required', 'exists:inventory_categories,id'],
                'quantity' => ['required', 'integer', 'min:0'],
                'minimum_stock_level' => ['required', 'integer', 'min:0'],
                'unit_of_measurement' => ['required', 'string', 'max:50'],
                'acquisition_date' => ['nullable', 'date'],
                'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
                'status' => ['required', Rule::in(array_keys(InventoryItem::STATUSES))],
                'condition_id' => ['required', 'exists:inventory_conditions,id'],
            ]);

            if ($validator->fails()) {
                return back()->withInput()->withErrors([
                    'items' => "Row {$rowNum} ({$code}): " . $validator->errors()->first(),
                ]);
            }

            $normalized[] = $candidate;
        }

        DB::transaction(function () use ($normalized, $user): void {
            foreach ($normalized as $entry) {
                $qty = (int) $entry['quantity'];
                $item = InventoryItem::create([
                    'property_code' => $entry['property_code'],
                    'name' => $entry['name'],
                    'category_id' => $entry['category_id'],
                    'brand_model' => $entry['brand_model'],
                    'serial_number' => $entry['serial_number'],
                    'quantity' => 0,
                    'available_quantity' => 0,
                    'minimum_stock_level' => $entry['minimum_stock_level'],
                    'unit_of_measurement' => $entry['unit_of_measurement'],
                    'acquisition_date' => $entry['acquisition_date'],
                    'acquisition_cost' => $entry['acquisition_cost'],
                    'supplier_name' => $entry['supplier_name'],
                    'status' => $entry['status'],
                    'condition_id' => $entry['condition_id'],
                    'location_id' => $entry['location_id'],
                    'remarks' => $entry['remarks'],
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                if ($qty > 0) {
                    $this->stockService->recordMovement(
                        item: $item,
                        movementType: StockMovement::TYPE_STOCK_IN,
                        quantity: $qty,
                        actor: $user,
                        remarks: 'Bulk inventory registration entry.'
                    );
                }
            }

            $this->auditLog->log(
                action: 'inventory.bulk_registered',
                module: 'inventory',
                newValues: ['count' => count($normalized)],
                description: 'Bulk registered ' . count($normalized) . ' inventory item(s).',
                actor: $user
            );
        });

        return redirect()
            ->route('inventory.index')
            ->with('success', count($normalized) . ' inventory item(s) registered successfully.');
    }

    public function serveImage(string $filename): BinaryFileResponse
    {
        $safe = basename($filename);
        $path = 'items/' . $safe;

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }

    private function suggestPropertyCode(string $categoryCode): string
    {
        $prefix = trim((string) $this->settings->get('property_code_prefix', 'PCB-SSC'));
        $base = strtoupper($prefix . '-' . $categoryCode);
        $count = InventoryItem::withTrashed()->where('property_code', 'like', $base . '-%')->count() + 1;

        do {
            $candidate = sprintf('%s-%04d', $base, $count);
            $count++;
        } while (InventoryItem::withTrashed()->where('property_code', $candidate)->exists());

        return $candidate;
    }
}
