@extends('layouts.app')

@section('page_title', 'Bulk Inventory Registration')
@section('page_subtitle', 'Register multiple inventory records at once via grid entry or CSV import')

@section('content')
<div class="space-y-6" x-data="{
    mode: 'grid',
    rows: [
        { property_code: '', name: '', category_id: '{{ $categories->first()?->id }}', brand_model: '', quantity: 1, unit_of_measurement: 'piece', condition_id: '{{ $conditions->firstWhere('slug', 'good')?->id ?? $conditions->first()?->id }}', location_id: '{{ $locations->first()?->id }}', acquisition_cost: '0.00' }
    ],
    addRow() {
        this.rows.push({ property_code: '', name: '', category_id: '{{ $categories->first()?->id }}', brand_model: '', quantity: 1, unit_of_measurement: 'piece', condition_id: '{{ $conditions->firstWhere('slug', 'good')?->id ?? $conditions->first()?->id }}', location_id: '{{ $locations->first()?->id }}', acquisition_cost: '0.00' });
    },
    removeRow(index) {
        if (this.rows.length > 1) this.rows.splice(index, 1);
    }
}">
    <!-- Mode Switcher -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-xs">
        <div class="flex gap-2">
            <button type="button" x-on:click="mode = 'grid'" :class="mode === 'grid' ? 'bg-maroon-900 text-white' : 'bg-gray-100 text-gray-700'" class="rounded-xl px-4 py-2 text-xs font-semibold transition">
                Interactive Multi-Row Grid
            </button>
            <button type="button" x-on:click="mode = 'csv'" :class="mode === 'csv' ? 'bg-maroon-900 text-white' : 'bg-gray-100 text-gray-700'" class="rounded-xl px-4 py-2 text-xs font-semibold transition">
                CSV File Import
            </button>
        </div>
        <a href="{{ route('inventory.bulk.template') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-maroon-200 bg-maroon-50 px-3.5 py-2 text-xs font-semibold text-maroon-900 hover:bg-maroon-100">
            <x-icon name="download" class="w-4 h-4" />
            Download CSV Template
        </a>
    </div>

    <!-- Interactive Grid Form -->
    <form x-show="mode === 'grid'" method="POST" action="{{ route('inventory.bulk.store') }}" class="space-y-4">
        @csrf
        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-xs">
            <table class="min-w-full divide-y divide-gray-200 text-xs">
                <thead class="bg-gray-50 text-left text-[11px] font-semibold text-gray-500 uppercase">
                    <tr>
                        <th class="px-3 py-3">Property Code *</th>
                        <th class="px-3 py-3">Item Name *</th>
                        <th class="px-3 py-3">Category *</th>
                        <th class="px-3 py-3">Brand / Model</th>
                        <th class="px-3 py-3 w-20">Qty *</th>
                        <th class="px-3 py-3 w-24">Unit *</th>
                        <th class="px-3 py-3">Condition *</th>
                        <th class="px-3 py-3">Location</th>
                        <th class="px-3 py-3 w-28">Cost (PHP)</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="(row, index) in rows" :key="index">
                        <tr>
                            <td class="p-2"><input type="text" :name="`items[${index}][property_code]`" x-model="row.property_code" required placeholder="PCB-SSC-..." class="!py-1.5 !text-xs font-mono uppercase"></td>
                            <td class="p-2"><input type="text" :name="`items[${index}][name]`" x-model="row.name" required placeholder="Item name" class="!py-1.5 !text-xs"></td>
                            <td class="p-2">
                                <select :name="`items[${index}][category_id]`" x-model="row.category_id" required class="!py-1.5 !text-xs">
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="p-2"><input type="text" :name="`items[${index}][brand_model]`" x-model="row.brand_model" class="!py-1.5 !text-xs"></td>
                            <td class="p-2"><input type="number" min="0" :name="`items[${index}][quantity]`" x-model="row.quantity" required class="!py-1.5 !text-xs"></td>
                            <td class="p-2"><input type="text" :name="`items[${index}][unit_of_measurement]`" x-model="row.unit_of_measurement" required class="!py-1.5 !text-xs"></td>
                            <td class="p-2">
                                <select :name="`items[${index}][condition_id]`" x-model="row.condition_id" required class="!py-1.5 !text-xs">
                                    @foreach ($conditions as $cond)
                                        <option value="{{ $cond->id }}">{{ $cond->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="p-2">
                                <select :name="`items[${index}][location_id]`" x-model="row.location_id" class="!py-1.5 !text-xs">
                                    <option value="">None</option>
                                    @foreach ($locations as $loc)
                                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="p-2"><input type="number" step="0.01" min="0" :name="`items[${index}][acquisition_cost]`" x-model="row.acquisition_cost" class="!py-1.5 !text-xs"></td>
                            <td class="p-2 text-center">
                                <button type="button" x-on:click="removeRow(index)" class="rounded-lg p-1.5 text-red-600 hover:bg-red-50" title="Remove row">
                                    <x-icon name="x" class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <button type="button" x-on:click="addRow()" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                <x-icon name="plus" class="w-4 h-4" />
                Add Another Row
            </button>
            <button type="submit" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-maroon-800">
                Register All Items
            </button>
        </div>
    </form>

    <!-- CSV Upload Form -->
    <form x-show="mode === 'csv'" x-cloak method="POST" action="{{ route('inventory.bulk.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
        @csrf
        <h3 class="text-sm font-bold text-charcoal">Import Inventory from CSV</h3>
        <p class="mt-1 text-xs text-gray-500">
            Use the official CSV template. Validations ensure unique property codes and atomic registration (if any row fails, no partial data is written).
        </p>
        <div class="mt-4">
            <input type="file" name="csv_file" accept=".csv,.txt" required class="block w-full text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-maroon-50 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-maroon-900">
        </div>
        <div class="mt-4 flex justify-end">
            <button type="submit" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-maroon-800">
                Upload &amp; Import CSV
            </button>
        </div>
    </form>
</div>
@endsection
