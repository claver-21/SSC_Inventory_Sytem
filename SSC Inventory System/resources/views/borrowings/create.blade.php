@extends('layouts.app')

@section('page_title', 'New Borrowing Request')
@section('page_subtitle', 'Submit an official PCB-SSC equipment borrowing request')

@section('content')
<form
    method="POST"
    action="{{ route('borrowings.store') }}"
    class="mx-auto max-w-4xl space-y-6"
    x-data="{
        items: [
            { inventory_item_id: '{{ old('items.0.inventory_item_id', $preselectedItemId ?? ($availableItems->first()?->id ?? '')) }}', quantity_requested: {{ old('items.0.quantity_requested', 1) }} }
        ],
        catalog: {{ Js::from($availableItems->keyBy('id')->map(fn ($i) => ['name' => $i->name, 'code' => $i->property_code, 'avail' => $i->available_quantity, 'unit' => $i->unit_of_measurement])) }},
        addRow() {
            this.items.push({ inventory_item_id: '', quantity_requested: 1 });
        },
        removeRow(idx) {
            if (this.items.length > 1) this.items.splice(idx, 1);
        },
        maxFor(id) {
            return this.catalog[id] ? this.catalog[id].avail : 999;
        }
    }"
>
    @csrf

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
        <h2 class="border-b border-gray-100 pb-3 text-sm font-bold text-charcoal">Borrower Information</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-xs font-semibold text-gray-700">Borrower Name *</label>
                <input type="text" name="borrower_name" value="{{ old('borrower_name', $user->name) }}" required class="mt-1">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">School ID *</label>
                <input type="text" name="school_id" value="{{ old('school_id', $user->school_id) }}" required placeholder="e.g., 24-12345" class="mt-1">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Department / Organization *</label>
                <input type="text" name="department_organization" value="{{ old('department_organization', $user->department ?: 'Supreme Student Council') }}" required class="mt-1">
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-xs font-semibold text-gray-700">Request Date *</label>
                <input type="date" name="request_date" value="{{ old('request_date', now()->format('Y-m-d')) }}" required class="mt-1">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Date Borrowed *</label>
                <input type="date" name="date_borrowed" value="{{ old('date_borrowed', now()->format('Y-m-d')) }}" required class="mt-1">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700">Expected Return Date *</label>
                <input type="date" name="expected_return_date" value="{{ old('expected_return_date', $defaultReturnDate) }}" required class="mt-1">
            </div>
        </div>

        <div class="mt-4">
            <label class="block text-xs font-semibold text-gray-700">Purpose of Borrowing *</label>
            <textarea name="purpose" rows="2" required placeholder="State event, activity, or official council task..." class="mt-1">{{ old('purpose') }}</textarea>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <div>
                <h2 class="text-sm font-bold text-charcoal">Equipment / Items Requested</h2>
                <p class="text-xs text-gray-500">Only items with available stock are listed. Requests exceeding available quantities are blocked.</p>
            </div>
            <button type="button" x-on:click="addRow()" class="inline-flex items-center gap-1 rounded-lg bg-maroon-50 px-3 py-1.5 text-xs font-semibold text-maroon-900 hover:bg-maroon-100">
                <x-icon name="plus" class="w-4 h-4" />
                Add Item
            </button>
        </div>

        @if ($availableItems->isEmpty())
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800">
                There are currently no available inventory items in stock to borrow.
            </div>
        @else
            <div class="mt-4 space-y-3">
                <template x-for="(row, idx) in items" :key="idx">
                    <div class="grid items-end gap-3 rounded-xl border border-gray-200 bg-gray-50/70 p-3 sm:grid-cols-12">
                        <div class="sm:col-span-7">
                            <label class="block text-[11px] font-semibold text-gray-600">Inventory Item *</label>
                            <select :name="`items[${idx}][inventory_item_id]`" x-model="row.inventory_item_id" required class="mt-1">
                                <option value="">Select available item...</option>
                                @foreach ($availableItems as $item)
                                    <option value="{{ $item->id }}">
                                        [{{ $item->property_code }}] {{ $item->name }} — Avail: {{ $item->available_quantity }} {{ $item->unit_of_measurement }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-[11px] font-semibold text-gray-600">
                                Quantity * <span x-show="catalog[row.inventory_item_id]" class="text-maroon-800" x-text="`(max ${maxFor(row.inventory_item_id)})`"></span>
                            </label>
                            <input
                                type="number"
                                min="1"
                                :max="maxFor(row.inventory_item_id)"
                                :name="`items[${idx}][quantity_requested]`"
                                x-model="row.quantity_requested"
                                required
                                class="mt-1"
                            >
                        </div>
                        <div class="sm:col-span-2 flex justify-end">
                            <button type="button" x-on:click="removeRow(idx)" class="rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">
                                Remove
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        @endif
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('borrowings.index') }}" class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
        <button type="submit" @disabled($availableItems->isEmpty()) class="rounded-xl bg-maroon-900 px-6 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-maroon-800 disabled:opacity-50">
            Submit Borrowing Request
        </button>
    </div>
</form>
@endsection
