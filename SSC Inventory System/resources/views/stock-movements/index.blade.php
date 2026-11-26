@extends('layouts.app')

@section('page_title', 'Stock & Inventory Movements')
@section('page_subtitle', 'Complete immutable ledger of stock inflows, outflows, borrowings, returns, transfers, and adjustments')

@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-gray-500">Every quantity change in the system creates an accountable stock movement record.</p>
        @permission('stock.record')
            <a href="{{ route('stock-movements.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-maroon-900 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-maroon-800">
                <x-icon name="plus" class="w-4 h-4" />
                Record Stock Movement
            </a>
        @endpermission
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('stock-movements.index') }}" class="grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-xs sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Reference, item, remarks..." class="lg:col-span-2">
        <select name="movement_type">
            <option value="">All Movement Types</option>
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected(request('movement_type') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="inventory_item_id">
            <option value="">All Items</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}" @selected(request('inventory_item_id') == $item->id)>{{ $item->property_code }} — {{ $item->name }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" title="Date From">
        <div class="flex gap-2">
            <input type="date" name="date_to" value="{{ request('date_to') }}" title="Date To" class="flex-1">
            <button type="submit" class="rounded-lg bg-maroon-900 px-3.5 py-2 text-xs font-semibold text-white hover:bg-maroon-800">Go</button>
        </div>
    </form>

    @if ($movements->isEmpty())
        <x-empty-state title="No stock movements recorded" message="Register inventory or record a stock movement to populate the ledger." icon="stock" />
    @else
        <div class="table-shell">
            <div class="table-scroll overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Item</th>
                            <th>Movement Type</th>
                            <th class="text-center">Qty</th>
                            <th class="text-center">Total Stock</th>
                            <th class="text-center">Available Stock</th>
                            <th>Processed By</th>
                            <th>Date</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($movements as $mov)
                            <tr class="hover:bg-maroon-50/30">
                                <td class="whitespace-nowrap px-4 py-3 font-mono font-semibold text-maroon-900">{{ $mov->transaction_reference }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('inventory.show', $mov->inventory_item_id) }}" class="font-semibold text-charcoal hover:underline">
                                        {{ $mov->inventoryItem?->name }}
                                    </a>
                                    <p class="font-mono text-[11px] text-gray-500">{{ $mov->inventoryItem?->property_code }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-full bg-maroon-50 px-2.5 py-0.5 text-[11px] font-semibold text-maroon-900">
                                        {{ $mov->type_label }}
                                    </span>
                                    @if ($mov->movement_type === 'transfer')
                                        <p class="mt-0.5 text-[10px] text-gray-500">
                                            {{ $mov->fromLocation?->name ?? 'Unassigned' }} &rarr; {{ $mov->toLocation?->name }}
                                        </p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center font-bold {{ $mov->quantity > 0 ? 'text-charcoal' : 'text-red-700' }}">
                                    {{ $mov->quantity > 0 ? $mov->quantity : $mov->quantity }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">{{ $mov->previous_quantity }} &rarr; <strong>{{ $mov->new_quantity }}</strong></td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">{{ $mov->previous_available_quantity }} &rarr; <strong>{{ $mov->new_available_quantity }}</strong></td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ $mov->processor?->name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-500">{{ $mov->transaction_date?->format('M d, Y h:i A') }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $mov->remarks ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-3">{{ $movements->links() }}</div>
        </div>
    @endif
</div>
@endsection
