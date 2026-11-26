@extends('layouts.app')

@section('page_title', 'Register Inventory Item')
@section('page_subtitle', 'Record a new organizational property, equipment, or supply into the PCB-SSC registry')

@section('content')
<form method="POST" action="{{ route('inventory.store') }}" class="mx-auto max-w-4xl space-y-6">
    @csrf

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
        <h2 class="border-b border-gray-100 pb-3 text-sm font-bold text-charcoal">Item Information</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-semibold text-gray-700">Property Code / Inventory Number *</label>
                <input type="text" name="property_code" value="{{ old('property_code', $suggestedCode) }}" required class="mt-1 font-mono uppercase">
                <p class="mt-1 text-[11px] text-gray-400">Must be unique across all active and archived records.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Item Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g., Wireless Dual Microphone Set" class="mt-1">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-700">Description</label>
                <textarea name="description" rows="2" placeholder="Specifications, accessories included, color, or technical notes..." class="mt-1">{{ old('description') }}</textarea>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
        <h2 class="border-b border-gray-100 pb-3 text-sm font-bold text-charcoal">Stock &amp; Availability</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-semibold text-gray-700">Initial Quantity *</label>
                <input type="number" name="quantity" min="0" value="{{ old('quantity', 1) }}" required class="mt-1">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Unit of Measurement *</label>
                <input type="text" name="unit_of_measurement" value="{{ old('unit_of_measurement', 'piece') }}" required placeholder="piece, unit, set, box, ream" class="mt-1">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Low Stock Alert Level *</label>
                <input type="number" name="minimum_stock_level" min="0" value="{{ old('minimum_stock_level', $defaultMinStock) }}" required class="mt-1">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Availability Status *</label>
                <select name="status" required class="mt-1">
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', 'available') === $key)>{{ $label }}</option>
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
            Register Inventory Item
        </button>
    </div>
</form>
@endsection
