@extends('layouts.app')

@section('page_title', $item->name)
@section('page_subtitle', 'Property Code: ' . $item->property_code)

@section('content')
<div class="space-y-6" x-data="{ tab: 'movements' }">
    <!-- Top Header Card -->
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
        <div class="flex flex-col justify-between gap-6 lg:flex-row">
            <div class="flex flex-col gap-5 sm:flex-row">
                @if ($item->imageUrl())
                    <img src="{{ $item->imageUrl() }}" alt="{{ $item->name }}" class="h-36 w-36 shrink-0 rounded-2xl border border-gray-200 object-cover shadow-xs">
                @else
                    <div class="flex h-36 w-36 shrink-0 items-center justify-center rounded-2xl border border-maroon-100 bg-maroon-50 text-maroon-800">
                        <x-icon name="inventory" class="w-12 h-12" />
                    </div>
                @endif

                <div class="space-y-2.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-lg bg-maroon-900 px-2.5 py-1 font-mono text-xs font-bold text-white">
                            {{ $item->property_code }}
                        </span>
                        <x-status-badge :status="$item->status" />
                        <x-condition-badge :condition="$item->condition" />
                        @if ($item->trashed())
                            <x-status-badge status="archived" label="Archived Record" />
                        @endif
                    </div>

                    <h2 class="text-xl font-bold text-charcoal">{{ $item->name }}</h2>
                    <p class="text-xs text-gray-600">{{ $item->description ?: 'No detailed description recorded.' }}</p>

                    <div class="flex flex-wrap gap-x-6 gap-y-1.5 pt-1 text-xs text-gray-600">
                        <span><strong>Category:</strong> {{ $item->category?->name ?? '—' }}</span>
                        <span><strong>Brand/Model:</strong> {{ $item->brand_model ?: '—' }}</span>
                        <span><strong>Serial No.:</strong> <span class="font-mono">{{ $item->serial_number ?: '—' }}</span></span>
                    </div>
                    @if ($item->sensitive_notes && auth()->user()->hasPermission('inventory.manage'))
                        <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50/70 px-3 py-1.5 text-xs text-amber-900">
                            <strong>Sensitive Custody Note (Encrypted):</strong> {{ $item->sensitive_notes }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- Action Controls -->
            <div class="flex flex-wrap items-start gap-2 lg:justify-end">
                @if (!$item->trashed())
                    @permission('borrowings.request')
                        @if ($item->available_quantity > 0 && $item->status === 'available')
                            <a href="{{ route('borrowings.create', ['item_id' => $item->id]) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-maroon-900 px-3.5 py-2 text-xs font-semibold text-white hover:bg-maroon-800">
                                <x-icon name="borrowing" class="w-4 h-4" />
                                Borrow Item
                            </a>
                        @endif
                    @endpermission

                    @permission('incidents.report')
                        <button type="button" x-on:click="$dispatch('open-modal', 'report-incident')" class="inline-flex items-center gap-1.5 rounded-xl border border-orange-200 bg-orange-50 px-3.5 py-2 text-xs font-semibold text-orange-800 hover:bg-orange-100">
                            <x-icon name="alert" class="w-4 h-4" />
                            Report Damaged / Missing
                        </button>
                    @endpermission

                    @permission('inventory.manage')
                        <button type="button" x-on:click="$dispatch('open-modal', 'update-condition')" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Update Condition
                        </button>
                        <a href="{{ route('inventory.edit', $item) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-maroon-200 bg-maroon-50 px-3.5 py-2 text-xs font-semibold text-maroon-900 hover:bg-maroon-100">
                            <x-icon name="edit" class="w-4 h-4" />
                            Edit
                        </a>
                    @endpermission

                    @permission('inventory.archive')
                        <form method="POST" action="{{ route('inventory.archive', $item) }}" onsubmit="return confirm('{{ config('auth.mfa_enabled', true) ? 'Archive/delete this inventory item? Step-Up 2FA verification is required.' : 'Archive this inventory item?' }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-3.5 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">
                                <x-icon name="archive" class="w-4 h-4" />
                                {{ config('auth.mfa_enabled', true) ? 'Archive (Step-Up MFA)' : 'Archive Item' }}
                            </button>
                        </form>
                    @endpermission
                @else
                    @permission('inventory.archive')
                        <form method="POST" action="{{ route('inventory.restore', $item->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                                <x-icon name="restore" class="w-4 h-4" />
                                Restore Item
                            </button>
                        </form>
                    @endpermission
                @endif
            </div>
        </div>

        <!-- Metadata Grid -->
        <div class="mt-6 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl bg-gray-50 p-3.5">
                <p class="text-[11px] font-semibold text-gray-500 uppercase">Stock Quantities</p>
                <p class="mt-1 text-lg font-bold text-charcoal">
                    {{ $item->available_quantity }} <span class="text-xs font-normal text-gray-500">avail / {{ $item->quantity }} {{ $item->unit_of_measurement }}</span>
                </p>
                <p class="text-[11px] text-gray-500">Min alert level: {{ $item->minimum_stock_level }} · Borrowed: {{ $item->borrowed_quantity }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 p-3.5">
                <p class="text-[11px] font-semibold text-gray-500 uppercase">Storage &amp; Custodian</p>
                <p class="mt-1 text-sm font-bold text-charcoal">{{ $item->location?->name ?? 'Unassigned Location' }}</p>
                <p class="text-[11px] text-gray-500">Officer: {{ $item->responsibleUser?->name ?? 'Unassigned' }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 p-3.5">
                <p class="text-[11px] font-semibold text-gray-500 uppercase">Acquisition Info</p>
                <p class="mt-1 text-sm font-bold text-charcoal">₱{{ number_format((float) $item->acquisition_cost, 2) }}</p>
                <p class="text-[11px] text-gray-500">
                    {{ $item->acquisition_date?->format('M d, Y') ?? 'No date' }} · {{ $item->supplier?->name ?? $item->supplier_name ?? 'N/A' }}
                </p>
            </div>
            <div class="rounded-xl bg-gray-50 p-3.5">
                <p class="text-[11px] font-semibold text-gray-500 uppercase">Record Timestamps</p>
                <p class="mt-1 text-xs font-semibold text-charcoal">Created: {{ $item->created_at?->format('M d, Y h:i A') }}</p>
                <p class="text-[11px] text-gray-500">Updated: {{ $item->updated_at?->format('M d, Y h:i A') }}</p>
            </div>
        </div>
    </div>

    <!-- History Tabs -->
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xs">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 pt-3">
            <div class="flex gap-4 text-xs font-semibold">
                <button type="button" x-on:click="tab = 'movements'" :class="tab === 'movements' ? 'border-b-2 border-maroon-900 text-maroon-900' : 'text-gray-500 hover:text-charcoal'" class="pb-3">
                    Stock Movements ({{ $movements->count() }})
                </button>
                <button type="button" x-on:click="tab = 'borrowings'" :class="tab === 'borrowings' ? 'border-b-2 border-maroon-900 text-maroon-900' : 'text-gray-500 hover:text-charcoal'" class="pb-3">
                    Borrowing History ({{ $borrowings->count() }})
                </button>
                <button type="button" x-on:click="tab = 'incidents'" :class="tab === 'incidents' ? 'border-b-2 border-maroon-900 text-maroon-900' : 'text-gray-500 hover:text-charcoal'" class="pb-3">
                    Incident Reports ({{ $incidents->count() }})
                </button>
            </div>
            @permission('stock.record')
                @if (!$item->trashed())
                    <a href="{{ route('stock-movements.create', ['inventory_item_id' => $item->id]) }}" class="mb-2 inline-flex items-center gap-1 rounded-lg bg-maroon-50 px-3 py-1.5 text-xs font-semibold text-maroon-900 hover:bg-maroon-100">
                        <x-icon name="plus" class="w-3.5 h-3.5" />
                        Record Stock Movement
                    </a>
                @endif
            @endpermission
        </div>

        <!-- Movements Tab -->
        <div x-show="tab === 'movements'" class="overflow-x-auto">
            @if ($movements->isEmpty())
                <div class="p-6 text-center text-xs text-gray-500">No stock movements recorded for this item yet.</div>
            @else
                <table class="min-w-full divide-y divide-gray-100 text-xs">
                    <thead class="bg-gray-50 text-left text-[11px] font-semibold text-gray-500 uppercase">
                        <tr>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3 text-center">Qty</th>
                            <th class="px-5 py-3 text-center">Total Stock</th>
                            <th class="px-5 py-3 text-center">Available</th>
                            <th class="px-5 py-3">Processed By</th>
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($movements as $mov)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3 font-mono font-semibold text-maroon-900">{{ $mov->transaction_reference }}</td>
                                <td class="whitespace-nowrap px-5 py-3">{{ $mov->type_label }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-center font-bold">{{ $mov->quantity > 0 ? '+' . $mov->quantity : $mov->quantity }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-center">{{ $mov->previous_quantity }} &rarr; {{ $mov->new_quantity }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-center">{{ $mov->previous_available_quantity }} &rarr; {{ $mov->new_available_quantity }}</td>
                                <td class="whitespace-nowrap px-5 py-3">{{ $mov->processor?->name }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-gray-500">{{ $mov->transaction_date?->format('M d, Y h:i A') }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $mov->remarks ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <!-- Borrowings Tab -->
        <div x-show="tab === 'borrowings'" x-cloak class="overflow-x-auto">
            @if ($borrowings->isEmpty())
                <div class="p-6 text-center text-xs text-gray-500">This item has not been included in any borrowing requests yet.</div>
            @else
                <table class="min-w-full divide-y divide-gray-100 text-xs">
                    <thead class="bg-gray-50 text-left text-[11px] font-semibold text-gray-500 uppercase">
                        <tr>
                            <th class="px-5 py-3">Transaction</th>
                            <th class="px-5 py-3">Borrower</th>
                            <th class="px-5 py-3 text-center">Requested / Approved / Returned</th>
                            <th class="px-5 py-3">Borrowed Date</th>
                            <th class="px-5 py-3">Expected Return</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($borrowings as $bi)
                            @php $br = $bi->borrowingRequest; @endphp
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3 font-mono font-semibold text-maroon-900">
                                    @if (auth()->user()->hasPermission('borrowings.view_all') || $br?->requester_user_id === auth()->id())
                                        <a href="{{ route('borrowings.show', $br) }}" class="hover:underline">{{ $br?->transaction_code }}</a>
                                    @else
                                        {{ $br?->transaction_code }}
                                    @endif
                                </td>
                                <td class="px-5 py-3">{{ $br?->borrower_name }} ({{ $br?->department_organization }})</td>
                                <td class="whitespace-nowrap px-5 py-3 text-center">{{ $bi->quantity_requested }} / {{ $bi->quantity_approved }} / {{ $bi->quantity_returned }}</td>
                                <td class="whitespace-nowrap px-5 py-3">{{ $br?->date_borrowed?->format('M d, Y') }}</td>
                                <td class="whitespace-nowrap px-5 py-3">{{ $br?->expected_return_date?->format('M d, Y') }}</td>
                                <td class="whitespace-nowrap px-5 py-3"><x-status-badge :status="$br?->status ?? 'pending'" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <!-- Incidents Tab -->
        <div x-show="tab === 'incidents'" x-cloak class="divide-y divide-gray-100">
            @if ($incidents->isEmpty())
                <div class="p-6 text-center text-xs text-gray-500">No damaged or missing incidents reported for this item.</div>
            @else
                @foreach ($incidents as $inc)
                    <div class="p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-bold text-maroon-900">{{ $inc->report_code }}</span>
                                <x-status-badge :status="$inc->incident_type" />
                                <x-status-badge :status="$inc->status" />
                            </div>
                            <span class="text-[11px] text-gray-500">Reported by {{ $inc->reporter?->name }} on {{ $inc->created_at?->format('M d, Y h:i A') }}</span>
                        </div>
                        <p class="mt-2 text-xs text-gray-700"><strong>Affected Qty:</strong> {{ $inc->affected_quantity }} — {{ $inc->description }}</p>

                        @if ($inc->resolution_notes)
                            <p class="mt-1.5 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600">
                                <strong>Resolution ({{ $inc->resolver?->name }}):</strong> {{ $inc->resolution_notes }}
                            </p>
                        @endif

                        @permission('incidents.resolve')
                            @if (! in_array($inc->status, ['resolved', 'lessons_learned'], true))
                                <form method="POST" action="{{ route('incidents.resolve', $inc) }}" class="mt-3 grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3 sm:grid-cols-4">
                                    @csrf
                                    <div>
                                        <select name="status" class="!py-1.5 !text-xs">
                                            <option value="investigating" @selected($inc->status === 'investigating')>Investigating</option>
                                            <option value="contained" @selected($inc->status === 'contained')>Contained</option>
                                            <option value="resolved" selected>Mark Resolved</option>
                                            <option value="lessons_learned">Lessons Learned</option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <input type="text" name="resolution_notes" required placeholder="Resolution notes..." class="!py-1.5 !text-xs">
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="inline-flex items-center gap-1.5 text-[11px] text-gray-700">
                                            <input type="checkbox" name="apply_stock_movement" value="1">
                                            <span>Deduct stock</span>
                                        </label>
                                        <button type="submit" class="rounded-lg bg-maroon-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-maroon-800">Update</button>
                                    </div>
                                </form>
                            @endif
                        @endpermission
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

<!-- Report Damaged / Missing Modal -->
@permission('incidents.report')
<x-modal name="report-incident" title="Report Damaged or Missing Equipment" maxWidth="md">
    <form method="POST" action="{{ route('incidents.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="inventory_item_id" value="{{ $item->id }}">
        <div>
            <label class="block text-xs font-semibold text-gray-700">Incident Type *</label>
            <select name="incident_type" required class="mt-1">
                <option value="damaged">Damaged Equipment</option>
                <option value="missing">Missing Equipment</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700">Affected Quantity *</label>
            <input type="number" name="affected_quantity" min="1" max="{{ max(1, $item->quantity) }}" value="1" required class="mt-1">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700">Incident Description *</label>
            <textarea name="description" rows="3" required placeholder="Describe what happened, when it was noticed, and any details..." class="mt-1"></textarea>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" x-on:click="$dispatch('close-modal', 'report-incident')" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600">Cancel</button>
            <button type="submit" class="rounded-lg bg-maroon-900 px-4 py-2 text-xs font-semibold text-white hover:bg-maroon-800">Submit Report</button>
        </div>
    </form>
</x-modal>
@endpermission

<!-- Quick Condition Update Modal -->
@permission('inventory.manage')
<x-modal name="update-condition" title="Update Physical Condition & Status" maxWidth="md">
    <form method="POST" action="{{ route('inventory.condition', $item) }}" class="space-y-4">
        @csrf
        @method('PATCH')
        <div>
            <label class="block text-xs font-semibold text-gray-700">Physical Condition *</label>
            <select name="condition_id" required class="mt-1">
                @foreach ($conditions as $cond)
                    <option value="{{ $cond->id }}" @selected($item->condition_id === $cond->id)>{{ $cond->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700">Availability Status *</label>
            <select name="status" required class="mt-1">
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($item->status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700">Remarks</label>
            <textarea name="remarks" rows="2" class="mt-1">{{ $item->remarks }}</textarea>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" x-on:click="$dispatch('close-modal', 'update-condition')" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600">Cancel</button>
            <button type="submit" class="rounded-lg bg-maroon-900 px-4 py-2 text-xs font-semibold text-white hover:bg-maroon-800">Save</button>
        </div>
    </form>
</x-modal>
@endpermission
@endsection
