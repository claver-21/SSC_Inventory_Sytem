@extends('layouts.app')

@section('page_title', 'Record Stock Movement')
@section('page_subtitle', 'Log an inventory inflow, outflow, transfer, adjustment, or write-off with full accountability')

@section('content')
<form
    method="POST"
    action="{{ route('stock-movements.store') }}"
    class="mx-auto max-w-2xl space-y-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-xs"
    x-data="{
        movementType: '{{ old('movement_type', 'stock_in') }}',
        selectedItem: '{{ old('inventory_item_id', $preselectedItemId ?? ($items->first()?->id ?? '')) }}',
        itemsMap: {{ Js::from($items->keyBy('id')->map(fn ($i) => ['qty' => $i->quantity, 'avail' => $i->available_quantity, 'loc' => $i->location?->name ?? 'Unassigned', 'unit' => $i->unit_of_measurement])) }}
    }"
>
    @csrf

    <div>
        <label class="block text-xs font-semibold text-gray-700">Inventory Item *</label>
        <select name="inventory_item_id" x-model="selectedItem" required class="mt-1">
            @foreach ($items as $item)
                <option value="{{ $item->id }}">
                    [{{ $item->property_code }}] {{ $item->name }} (Avail: {{ $item->available_quantity }} / Total: {{ $item->quantity }})
                </option>
            @endforeach
        </select>
        <template x-if="itemsMap[selectedItem]">
            <p class="mt-1.5 rounded-lg bg-maroon-50/70 px-3 py-1.5 text-[11px] text-maroon-900">
                Current Stock: <strong x-text="itemsMap[selectedItem].avail"></strong> available of
                <strong x-text="itemsMap[selectedItem].qty"></strong> <span x-text="itemsMap[selectedItem].unit"></span>
                · Location: <strong x-text="itemsMap[selectedItem].loc"></strong>
            </p>
        </template>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="block text-xs font-semibold text-gray-700">Movement Type *</label>
            <select name="movement_type" x-model="movementType" required class="mt-1">
                @foreach ($manualTypes as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700">Quantity *</label>
            <input type="number" name="quantity" min="1" value="{{ old('quantity', 1) }}" required class="mt-1">
        </div>
    </div>

    <div x-show="movementType === 'adjustment'" x-cloak>
        <label class="block text-xs font-semibold text-gray-700">Adjustment Direction *</label>
        <select name="adjustment_direction" class="mt-1">
            <option value="increase" @selected(old('adjustment_direction') === 'increase')>Increase Total &amp; Available Stock (+)</option>
            <option value="decrease" @selected(old('adjustment_direction') === 'decrease')>Decrease Total &amp; Available Stock (-)</option>
        </select>
    </div>

    <div x-show="movementType === 'transfer'" x-cloak>
        <label class="block text-xs font-semibold text-gray-700">Destination Storage Location *</label>
        <select name="to_location_id" class="mt-1">
            <option value="">Select destination location</option>
            @foreach ($locations as $loc)
                <option value="{{ $loc->id }}" @selected(old('to_location_id') == $loc->id)>{{ $loc->name }} ({{ $loc->building }})</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-semibold text-gray-700">Transaction Date</label>
        <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->format('Y-m-d')) }}" class="mt-1">
    </div>

    <div>
        <label class="block text-xs font-semibold text-gray-700">Remarks / Justification *</label>
        <textarea name="remarks" rows="3" required placeholder="Explain the reason for this stock movement..." class="mt-1">{{ old('remarks') }}</textarea>
    </div>

    <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
        <a href="{{ route('stock-movements.index') }}" class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
        <button type="submit" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-maroon-800">
            Save Stock Movement
        </button>
    </div>
</form>
@endsection
