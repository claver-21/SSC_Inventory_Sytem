@extends('layouts.app')

@section('page_title', 'Record Acquisition')
@section('page_subtitle', 'Log a new equipment or supply purchase with automatic total cost calculation')

@section('content')
<form
    method="POST"
    action="{{ route('procurement.store') }}"
    enctype="multipart/form-data"
    class="mx-auto max-w-3xl space-y-6"
    x-data="{
        qty: {{ old('quantity_acquired', 1) }},
        unitCost: {{ old('unit_cost', 0) }},
        syncMode: '{{ old('sync_mode', 'none') }}',
        get totalCost() {
            return (Number(this.qty || 0) * Number(this.unitCost || 0)).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }"
>
    @csrf

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs space-y-4">
        <h2 class="border-b border-gray-100 pb-3 text-sm font-bold text-charcoal">Purchase &amp; Cost Details</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-semibold text-gray-700">Purchase Reference Number *</label>
                <input type="text" name="purchase_reference" value="{{ old('purchase_reference', $suggestedRef) }}" required class="mt-1 font-mono uppercase">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Purchase Date *</label>
                <input type="date" name="purchase_date" value="{{ old('purchase_date', now()->format('Y-m-d')) }}" required class="mt-1">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-700">Item Name *</label>
                <input type="text" name="item_name" value="{{ old('item_name') }}" required placeholder="e.g., Portable PA Speaker System" class="mt-1">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Category</label>
                <select name="category_id" class="mt-1">
                    <option value="">Select category (optional)</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Funding Source *</label>
                <input type="text" name="funding_source" value="{{ old('funding_source', 'SSC General Fund') }}" required placeholder="e.g., SSC General Fund, Institutional Subsidy" class="mt-1">
            </div>
        </div>

        <div class="grid gap-4 rounded-xl bg-maroon-50/50 p-4 sm:grid-cols-3">
            <div>
                <label class="block text-xs font-semibold text-gray-700">Quantity Acquired *</label>
                <input type="number" name="quantity_acquired" min="1" x-model="qty" required class="mt-1">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Unit Cost (PHP) *</label>
                <input type="number" step="0.01" min="0" name="unit_cost" x-model="unitCost" required class="mt-1">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Calculated Total Cost</label>
                <div class="mt-1 rounded-lg border border-maroon-200 bg-white px-3 py-2 text-sm font-bold text-maroon-900">
                    ₱<span x-text="totalCost"></span>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-semibold text-gray-700">Registered Supplier</label>
                <select name="supplier_id" class="mt-1">
                    <option value="">Select supplier (optional)</option>
                    @foreach ($suppliers as $sup)
                        <option value="{{ $sup->id }}" @selected(old('supplier_id') == $sup->id)>{{ $sup->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Supplier Name (if unlisted)</label>
                <input type="text" name="supplier_name" value="{{ old('supplier_name') }}" class="mt-1">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-700">Receipt / Supporting Document (PDF, JPG, PNG, max 5MB)</label>
                <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-maroon-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-maroon-900">
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs space-y-4">
        <h2 class="border-b border-gray-100 pb-3 text-sm font-bold text-charcoal">Inventory Stock Synchronization</h2>

        <div>
            <label class="block text-xs font-semibold text-gray-700">Synchronize to Inventory Registry?</label>
            <select name="sync_mode" x-model="syncMode" class="mt-1">
                <option value="none">Record purchase expense only (do not alter stock yet)</option>
                <option value="existing">Add acquired quantity to an existing Inventory Item (Stock In)</option>
                <option value="new_item">Register a brand new Inventory Item &amp; Stock In automatically</option>
            </select>
        </div>

        <div x-show="syncMode === 'existing'" x-cloak>
            <label class="block text-xs font-semibold text-gray-700">Select Existing Inventory Item</label>
            <select name="inventory_item_id" class="mt-1">
                <option value="">Choose item...</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}">[{{ $item->property_code }}] {{ $item->name }}</option>
                @endforeach
            </select>
        </div>

        <div x-show="syncMode === 'new_item'" x-cloak class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-xs font-semibold text-gray-700">New Property Code *</label>
                <input type="text" name="new_property_code" value="{{ old('new_property_code') }}" placeholder="PCB-SSC-..." class="mt-1 font-mono uppercase">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Condition</label>
                <select name="new_condition_id" class="mt-1">
                    @foreach ($conditions as $cond)
                        <option value="{{ $cond->id }}">{{ $cond->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Storage Location</label>
                <select name="new_location_id" class="mt-1">
                    <option value="">Unassigned</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700">Remarks</label>
            <textarea name="remarks" rows="2" class="mt-1">{{ old('remarks') }}</textarea>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('procurement.index') }}" class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
        <button type="submit" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-maroon-800">
            Record Acquisition
        </button>
    </div>
</form>
@endsection
