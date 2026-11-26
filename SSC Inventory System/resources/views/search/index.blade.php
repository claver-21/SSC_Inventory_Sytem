@extends('layouts.app')

@section('page_title', 'Global Search')
@section('page_subtitle', $q ? 'Search results for "' . $q . '"' : 'Search inventory items, borrowing transactions, and procurement records')

@section('content')
<div class="space-y-6">
    <form method="GET" action="{{ route('search.index') }}" class="flex gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-xs">
        <input type="search" name="q" value="{{ $q }}" placeholder="Enter property code, item name, borrower name, transaction code, serial number..." class="flex-1">
        <button type="submit" class="rounded-xl bg-maroon-900 px-5 py-2 text-xs font-semibold text-white hover:bg-maroon-800">
            Search
        </button>
    </form>

    @if ($q === '')
        <x-empty-state title="Enter a search term" message="Search across PCB-SSC property codes, item names, serial numbers, borrowing records, and purchase references." icon="search" />
    @elseif ($items->isEmpty() && $borrowings->isEmpty() && $acquisitions->isEmpty())
        <x-empty-state title="No matching records" message="Try a different keyword or property code." icon="search" />
    @else
        @if ($items->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white shadow-xs">
                <div class="border-b border-gray-100 px-5 py-3.5 text-sm font-bold text-charcoal">
                    Inventory Items ({{ $items->count() }})
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach ($items as $item)
                        <a href="{{ route('inventory.show', $item) }}" class="flex items-center justify-between px-5 py-3 text-xs hover:bg-maroon-50/40">
                            <div>
                                <p class="font-semibold text-charcoal"><span class="font-mono text-maroon-900">{{ $item->property_code }}</span> — {{ $item->name }}</p>
                                <p class="text-[11px] text-gray-500">{{ $item->category?->name }} · {{ $item->location?->name ?? 'Unassigned' }} · Avail: {{ $item->available_quantity }}/{{ $item->quantity }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-status-badge :status="$item->status" />
                                <x-condition-badge :condition="$item->condition" />
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($borrowings->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white shadow-xs">
                <div class="border-b border-gray-100 px-5 py-3.5 text-sm font-bold text-charcoal">
                    Borrowing Transactions ({{ $borrowings->count() }})
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach ($borrowings as $br)
                        <a href="{{ route('borrowings.show', $br) }}" class="flex items-center justify-between px-5 py-3 text-xs hover:bg-maroon-50/40">
                            <div>
                                <p class="font-semibold text-charcoal"><span class="font-mono text-maroon-900">{{ $br->transaction_code }}</span> — {{ $br->borrower_name }}</p>
                                <p class="text-[11px] text-gray-500">{{ $br->department_organization }} · Due {{ $br->expected_return_date->format('M d, Y') }}</p>
                            </div>
                            <x-status-badge :status="$br->status" />
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($acquisitions->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white shadow-xs">
                <div class="border-b border-gray-100 px-5 py-3.5 text-sm font-bold text-charcoal">
                    Procurement Acquisitions ({{ $acquisitions->count() }})
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach ($acquisitions as $acq)
                        <a href="{{ route('procurement.index', ['search' => $acq->purchase_reference]) }}" class="flex items-center justify-between px-5 py-3 text-xs hover:bg-maroon-50/40">
                            <div>
                                <p class="font-semibold text-charcoal"><span class="font-mono text-maroon-900">{{ $acq->purchase_reference }}</span> — {{ $acq->item_name }}</p>
                                <p class="text-[11px] text-gray-500">{{ $acq->purchase_date->format('M d, Y') }} · {{ $acq->supplier?->name ?? $acq->supplier_name ?? 'N/A' }}</p>
                            </div>
                            <span class="font-bold text-charcoal">₱{{ number_format((float) $acq->total_cost, 2) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
