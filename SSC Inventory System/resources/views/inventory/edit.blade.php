@extends('layouts.app')

@section('page_title', 'Edit Inventory Item')
@section('page_subtitle', $item->property_code . ' — ' . $item->name)

@section('content')
<form method="POST" action="{{ route('inventory.update', $item) }}" class="mx-auto max-w-4xl space-y-6">
    @csrf
    @method('PUT')

    <div class="flex flex-col justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3.5 text-xs text-amber-900 sm:flex-row sm:items-center">
        <div>
            <span class="font-bold">Quantity Protection Active:</span>
            Stock levels ({{ $item->available_quantity }} available / {{ $item->quantity }} total) cannot be overwritten directly on the edit form.
        </div>
        @permission('stock.record')
            <a href="{{ route('stock-movements.create', ['inventory_item_id' => $item->id]) }}" class="shrink-0 rounded-lg bg-amber-800 px-3 py-1.5 font-semibold text-white hover:bg-amber-900">
                Record Stock Movement
            </a>
        @endpermission
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
        <h2 class="border-b border-gray-100 pb-3 text-sm font-bold text-charcoal">Item Information</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-semibold text-gray-700">Property Code *</label>
                <input type="text" name="property_code" value="{{ old('property_code', $item->property_code) }}" required class="mt-1 font-mono uppercase">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Item Name *</label>
                <input type="text" name="name" value="{{ old('name', $item->name) }}" required class="mt-1">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-700">Description</label>
                <textarea name="description" rows="2" class="mt-1">{{ old('description', $item->description) }}</textarea>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
        <h2 class="border-b border-gray-100 pb-3 text-sm font-bold text-charcoal">Stock &amp; Availability</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-xs font-semibold text-gray-700">Unit of Measurement *</label>
                <input type="text" name="unit_of_measurement" value="{{ old('unit_of_measurement', $item->unit_of_measurement) }}" required class="mt-1">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Low Stock Alert Level *</label>
                <input type="number" name="minimum_stock_level" min="0" value="{{ old('minimum_stock_level', $item->minimum_stock_level) }}" required class="mt-1">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Availability Status *</label>
                <select name="status" required class="mt-1">
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', $item->status) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('inventory.index') }}" class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
            Cancel
        </a>
        <button type="submit" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-maroon-800">
            Save Changes
        </button>
    </div>
</form>
@endsection
