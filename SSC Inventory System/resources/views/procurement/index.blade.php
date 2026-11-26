@extends('layouts.app')

@section('page_title', 'Procurement & Acquisitions')
@section('page_subtitle', 'Track organizational purchases, acquisition costs, funding sources, and official suppliers')

@section('content')
<div class="space-y-6" x-data="{ tab: '{{ $activeTab === 'suppliers' ? 'suppliers' : 'acquisitions' }}', editSupplier: null }">
    <!-- Summary KPI Strip -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card title="Total Acquisitions" :value="number_format($summary['count'])" subtitle="Recorded purchase entries" icon="procurement" tone="maroon" />
        <x-stat-card title="Total Acquisition Spend" :value="'₱' . number_format($summary['total_spend'], 2)" subtitle="Cumulative expenditure" icon="reports" tone="emerald" />
        <x-stat-card title="This Month Spend" :value="'₱' . number_format($summary['month_spend'], 2)" :subtitle="now()->format('F Y')" icon="stock" tone="blue" />
        <x-stat-card title="Units Acquired" :value="number_format($summary['total_units'])" :subtitle="$suppliers->where('is_active', true)->count() . ' active supplier(s)'" icon="inventory" tone="slate" />
    </div>

    <!-- Tabs & CTA -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2">
            <button type="button" x-on:click="tab = 'acquisitions'" :class="tab === 'acquisitions' ? 'bg-maroon-900 text-white shadow-xs' : 'bg-white text-gray-700 border border-gray-200'" class="rounded-xl px-4 py-2 text-xs font-semibold transition">
                Acquisition Ledger
            </button>
            <button type="button" x-on:click="tab = 'suppliers'" :class="tab === 'suppliers' ? 'bg-maroon-900 text-white shadow-xs' : 'bg-white text-gray-700 border border-gray-200'" class="rounded-xl px-4 py-2 text-xs font-semibold transition">
                Suppliers ({{ $suppliers->count() }})
            </button>
        </div>

        @permission('procurement.manage')
            <a href="{{ route('procurement.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-maroon-900 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-maroon-800">
                <x-icon name="plus" class="w-4 h-4" />
                Record Acquisition
            </a>
        @endpermission
    </div>

    <!-- Acquisitions Tab -->
    <div x-show="tab === 'acquisitions'" class="space-y-5">
        @if ($fundingSummary->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs">
                <h3 class="text-xs font-bold tracking-wide text-gray-500 uppercase">Expense Summary by Funding Source</h3>
                <div class="mt-3 grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($fundingSummary as $fs)
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-3 text-xs">
                            <p class="truncate font-semibold text-charcoal">{{ $fs->funding_source }}</p>
                            <p class="mt-1 text-base font-bold text-maroon-900">₱{{ number_format((float) $fs->total, 2) }}</p>
                            <p class="text-[11px] text-gray-500">{{ $fs->count }} transaction(s)</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="GET" action="{{ route('procurement.index') }}" class="grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-xs sm:grid-cols-5">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="PO reference, item, supplier..." class="sm:col-span-2">
            <select name="supplier_id">
                <option value="">All Suppliers</option>
                @foreach ($suppliers as $sup)
                    <option value="{{ $sup->id }}" @selected(request('supplier_id') == $sup->id)>{{ $sup->name }}</option>
                @endforeach
            </select>
            <select name="funding_source">
                <option value="">All Funding Sources</option>
                @foreach ($fundingSources as $src)
                    <option value="{{ $src }}" @selected(request('funding_source') === $src)>{{ $src }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-maroon-900 px-4 py-2 text-xs font-semibold text-white hover:bg-maroon-800">Filter</button>
        </form>

        @if ($acquisitions->isEmpty())
            <x-empty-state
                title="No acquisitions recorded"
                message="Record your first equipment or supply procurement to track expenses and sync stock."
                icon="procurement"
                :actionLabel="auth()->user()->hasPermission('procurement.manage') ? 'Record Acquisition' : null"
                :actionHref="auth()->user()->hasPermission('procurement.manage') ? route('procurement.create') : null"
            />
        @else
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 text-left text-[11px] font-semibold text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Purchase Ref</th>
                                <th class="px-4 py-3">Item Acquired</th>
                                <th class="px-4 py-3 text-center">Qty</th>
                                <th class="px-4 py-3 text-right">Unit Cost</th>
                                <th class="px-4 py-3 text-right">Total Cost</th>
                                <th class="px-4 py-3">Supplier &amp; Funding</th>
                                <th class="px-4 py-3">Date &amp; Recorded By</th>
                                <th class="px-4 py-3 text-right">Receipt</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($acquisitions as $acq)
                                <tr class="hover:bg-maroon-50/30">
                                    <td class="whitespace-nowrap px-4 py-3 font-mono font-bold text-maroon-900">{{ $acq->purchase_reference }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-charcoal">{{ $acq->item_name }}</p>
                                        <p class="text-[11px] text-gray-500">
                                            {{ $acq->category?->name ?? 'Uncategorized' }}
                                            @if ($acq->synced_to_inventory)
                                                · <span class="font-semibold text-emerald-700">Synced to Stock</span>
                                            @endif
                                        </p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center font-semibold">{{ $acq->quantity_acquired }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">₱{{ number_format((float) $acq->unit_cost, 2) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-bold text-charcoal">₱{{ number_format((float) $acq->total_cost, 2) }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-charcoal">{{ $acq->supplier?->name ?? $acq->supplier_name ?? '—' }}</p>
                                        <p class="text-[11px] text-gray-500">{{ $acq->funding_source }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                        <p>{{ $acq->purchase_date->format('M d, Y') }}</p>
                                        <p class="text-[11px] text-gray-400">By {{ $acq->recorder?->name }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        @if ($acq->receipt_path)
                                            <a href="{{ route('procurement.receipt', $acq) }}" target="_blank" class="rounded-lg border border-maroon-200 bg-maroon-50 px-2.5 py-1 text-[11px] font-semibold text-maroon-900 hover:bg-maroon-100">
                                                View Receipt
                                            </a>
                                        @else
                                            <span class="text-gray-400">None</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-gray-100 px-4 py-3">{{ $acquisitions->links() }}</div>
            </div>
        @endif
    </div>

    <!-- Suppliers Tab -->
    <div x-show="tab === 'suppliers'" x-cloak class="grid gap-6 lg:grid-cols-12">
        @permission('procurement.manage')
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs lg:col-span-4">
                <h3 class="text-sm font-bold text-charcoal">Add Supplier</h3>
                <form method="POST" action="{{ route('suppliers.store') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Supplier Name *</label>
                        <input type="text" name="name" required class="mt-1">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Contact Person</label>
                        <input type="text" name="contact_person" class="mt-1">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700">Phone</label>
                            <input type="text" name="phone" class="mt-1">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700">Email</label>
                            <input type="email" name="email" class="mt-1">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Address</label>
                        <textarea name="address" rows="2" class="mt-1"></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-maroon-900 px-4 py-2 text-xs font-semibold text-white hover:bg-maroon-800">Save Supplier</button>
                </form>
            </div>
        @endpermission

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs {{ auth()->user()->hasPermission('procurement.manage') ? 'lg:col-span-8' : 'lg:col-span-12' }}">
            @if ($suppliers->isEmpty())
                <div class="p-8 text-center text-xs text-gray-500">No suppliers registered yet.</div>
            @else
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 text-left text-[11px] font-semibold text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-3">Supplier</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3 text-center">Purchases</th>
                            <th class="px-4 py-3">Status</th>
                            @permission('procurement.manage')
                                <th class="px-4 py-3 text-right">Actions</th>
                            @endpermission
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($suppliers as $sup)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-charcoal">{{ $sup->name }}</p>
                                    <p class="text-[11px] text-gray-500">{{ $sup->address ?: '—' }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <p>{{ $sup->contact_person ?: '—' }}</p>
                                    <p class="text-[11px] text-gray-500">{{ $sup->phone }} {{ $sup->email ? '· ' . $sup->email : '' }}</p>
                                </td>
                                <td class="px-4 py-3 text-center font-semibold">{{ $sup->acquisitions_count }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$sup->is_active ? 'active' : 'inactive'" /></td>
                                @permission('procurement.manage')
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" x-on:click="editSupplier = {{ Js::from($sup) }}; $dispatch('open-modal', 'edit-supplier')" class="rounded-lg border border-gray-200 px-2.5 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-50">Edit</button>
                                    </td>
                                @endpermission
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <x-modal name="edit-supplier" title="Edit Supplier" maxWidth="md">
        <template x-if="editSupplier">
            <form method="POST" :action="`{{ url('/suppliers') }}/${editSupplier.id}`" class="space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Supplier Name *</label>
                    <input type="text" name="name" x-model="editSupplier.name" required class="mt-1">
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Contact Person</label>
                        <input type="text" name="contact_person" x-model="editSupplier.contact_person" class="mt-1">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Phone</label>
                        <input type="text" name="phone" x-model="editSupplier.phone" class="mt-1">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Email</label>
                    <input type="email" name="email" x-model="editSupplier.email" class="mt-1">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Address</label>
                    <textarea name="address" rows="2" x-model="editSupplier.address" class="mt-1"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                    <input type="checkbox" name="is_active" value="1" :checked="editSupplier.is_active">
                    <span>Active</span>
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'edit-supplier')" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600">Cancel</button>
                    <button type="submit" class="rounded-lg bg-maroon-900 px-4 py-2 text-xs font-semibold text-white">Update Supplier</button>
                </div>
            </form>
        </template>
    </x-modal>
</div>
@endsection
