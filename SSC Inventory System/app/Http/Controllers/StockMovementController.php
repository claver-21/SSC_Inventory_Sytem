<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    public function __construct(protected InventoryStockService $stockService)
    {
    }

    public function index(Request $request): View
    {
        $query = StockMovement::query()
            ->with(['inventoryItem', 'processor', 'fromLocation', 'toLocation']);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('transaction_reference', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('inventoryItem', fn ($item) => $item
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('property_code', 'like', "%{$search}%"));
            });
        }

        if ($type = $request->input('movement_type')) {
            $query->where('movement_type', $type);
        }

        if ($itemId = $request->input('inventory_item_id')) {
            $query->where('inventory_item_id', $itemId);
        }

        if ($processorId = $request->input('processed_by')) {
            $query->where('processed_by', $processorId);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }

        return view('stock-movements.index', [
            'movements' => $query->latest('transaction_date')->paginate(20)->withQueryString(),
            'types' => StockMovement::TYPES,
            'items' => InventoryItem::orderBy('name')->get(['id', 'property_code', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        $manualTypes = [
            StockMovement::TYPE_STOCK_IN => 'Stock In (Add Units)',
            StockMovement::TYPE_STOCK_OUT => 'Stock Out (Consume / Issue)',
            StockMovement::TYPE_ADJUSTMENT => 'Stock Adjustment (+/- Reconciliation)',
            StockMovement::TYPE_TRANSFER => 'Transfer Storage Location',
            StockMovement::TYPE_DAMAGED => 'Damaged Stock Flag',
            StockMovement::TYPE_MISSING => 'Missing Stock Write-off',
            StockMovement::TYPE_DISPOSAL => 'Property Disposal',
        ];

        return view('stock-movements.create', [
            'items' => InventoryItem::with('location')->orderBy('name')->get(),
            'locations' => InventoryLocation::where('is_active', true)->orderBy('name')->get(),
            'manualTypes' => $manualTypes,
            'preselectedItemId' => $request->input('inventory_item_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $allowedTypes = [
            StockMovement::TYPE_STOCK_IN,
            StockMovement::TYPE_STOCK_OUT,
            StockMovement::TYPE_ADJUSTMENT,
            StockMovement::TYPE_TRANSFER,
            StockMovement::TYPE_DAMAGED,
            StockMovement::TYPE_MISSING,
            StockMovement::TYPE_DISPOSAL,
        ];

        $validated = $request->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'movement_type' => ['required', Rule::in($allowedTypes)],
            'quantity' => ['required', 'integer', 'min:1'],
            'adjustment_direction' => ['nullable', Rule::in(['increase', 'decrease'])],
            'to_location_id' => ['nullable', 'exists:inventory_locations,id'],
            'transaction_date' => ['nullable', 'date'],
            'remarks' => ['required', 'string', 'min:3'],
        ]);

        $qty = (int) $validated['quantity'];
        if ($validated['movement_type'] === StockMovement::TYPE_ADJUSTMENT && ($validated['adjustment_direction'] ?? 'increase') === 'decrease') {
            $qty = -$qty;
        }

        $movement = $this->stockService->recordMovement(
            item: (int) $validated['inventory_item_id'],
            movementType: $validated['movement_type'],
            quantity: $qty,
            actor: $request->user(),
            remarks: $validated['remarks'],
            options: [
                'to_location_id' => $validated['to_location_id'] ?? null,
                'transaction_date' => ! empty($validated['transaction_date']) ? $validated['transaction_date'] : now(),
            ]
        );

        return redirect()
            ->route('stock-movements.index')
            ->with('success', "Stock movement {$movement->transaction_reference} recorded cleanly.");
    }
}
