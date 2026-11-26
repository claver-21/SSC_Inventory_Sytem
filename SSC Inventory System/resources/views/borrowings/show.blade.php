@extends('layouts.app')

@section('page_title', 'Borrowing Transaction ' . $borrowing->transaction_code)
@section('page_subtitle', 'Borrower: ' . $borrowing->borrower_name . ' (' . $borrowing->department_organization . ')')

@section('content')
<div class="space-y-6">
    @if ($borrowing->isOverdue())
        <div class="flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-xs text-red-900">
            <x-icon name="alert" class="w-5 h-5 shrink-0 text-red-700" />
            <div>
                <p class="font-bold">OVERDUE BY {{ $borrowing->overdue_days }} DAY(S)</p>
                <p>Expected return date was {{ $borrowing->expected_return_date->format('M d, Y') }}. Outstanding items must be returned and checked in immediately.</p>
            </div>
        </div>
    @endif

    @if ($borrowing->return_requested_at && $borrowing->isActiveLoan())
        <div class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3.5 text-xs text-amber-900">
            <x-icon name="bell" class="w-5 h-5 shrink-0 text-amber-700" />
            <div>
                <p class="font-bold">Return Request Submitted ({{ $borrowing->return_requested_at->format('M d, Y h:i A') }})</p>
                <p>{{ $borrowing->return_request_notes ?: 'Borrower notified the custodian that items are ready for return.' }}</p>
            </div>
        </div>
    @endif

    <!-- Transaction Summary Table -->
    <div class="table-shell">
        <div class="flex flex-col justify-between gap-4 border-b border-gray-100 bg-[#FAF6F7]/70 px-6 py-4 sm:flex-row sm:items-center">
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="rounded-lg bg-maroon-900 px-3 py-1 font-mono text-xs font-bold text-white">{{ $borrowing->transaction_code }}</span>
                <x-status-badge :status="$borrowing->status" />
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($borrowing->status === 'pending')
                    @permission('borrowings.approve')
                        <form method="POST" action="{{ route('borrowings.approve', $borrowing) }}" class="flex flex-wrap items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50/70 px-3 py-1.5">
                            @csrf
                            <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-900">
                                <input type="checkbox" name="count_verified" value="1" required>
                                <span>Double-checked item count &amp; condition</span>
                            </label>
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                <x-icon name="check" class="w-4 h-4" />
                                Approve &amp; Release
                            </button>
                        </form>
                        <button type="button" x-data x-on:click="$dispatch('open-modal', 'reject-borrowing')" class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">
                            <x-icon name="x" class="w-4 h-4" />
                            Reject Request
                        </button>
                    @endpermission
                @endif

                @if ($borrowing->isActiveLoan() && ! auth()->user()->isAdmin() && $borrowing->requester_user_id === auth()->id())
                    <form method="POST" action="{{ route('borrowings.return-request', $borrowing) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-700">
                            <x-icon name="restore" class="w-4 h-4" />
                            Return to Inventory
                        </button>
                    </form>
                @elseif ($borrowing->isActiveLoan() && auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('borrowings.notify-return', $borrowing) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-800 shadow-xs transition hover:bg-amber-100">
                            <x-icon name="bell" class="w-4 h-4" />
                            Notice to Return
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="table-scroll overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Borrower</th>
                        <th>Schedule &amp; Dates</th>
                        <th>Approval Info</th>
                        <th>Purpose &amp; Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="align-top">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-maroon-50 text-xs font-bold text-maroon-900 ring-1 ring-maroon-200">
                                    {{ strtoupper(substr($borrowing->borrower_name, 0, 2)) }}
                                </span>
                                <div>
                                    <p class="font-bold text-charcoal">{{ $borrowing->borrower_name }}</p>
                                    <p class="font-mono text-[11px] text-gray-500">School ID: {{ $borrowing->school_id }}</p>
                                    <p class="text-[11px] text-gray-500">{{ $borrowing->department_organization }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="align-top whitespace-nowrap text-xs">
                            <div class="space-y-1 tabular-nums">
                                <p><span class="text-gray-500">Requested:</span> <strong class="text-charcoal">{{ $borrowing->request_date->format('M d, Y') }}</strong></p>
                                <p><span class="text-gray-500">Borrowed:</span> <strong class="text-charcoal">{{ $borrowing->date_borrowed->format('M d, Y') }}</strong></p>
                                <p><span class="text-gray-500">Expected Return:</span> <strong class="text-charcoal">{{ $borrowing->expected_return_date->format('M d, Y') }}</strong></p>
                                @if ($borrowing->actual_return_date)
                                    <p class="text-emerald-700"><span>Returned:</span> <strong>{{ $borrowing->actual_return_date->format('M d, Y') }}</strong></p>
                                @endif
                            </div>
                        </td>
                        <td class="align-top whitespace-nowrap text-xs">
                            <div class="space-y-1">
                                <p><span class="text-gray-500">Submitted By:</span> <strong class="text-charcoal">{{ $borrowing->requester?->name ?? 'N/A' }}</strong></p>
                                <p><span class="text-gray-500">Reviewed By:</span> <strong class="text-charcoal">{{ $borrowing->approver?->name ?? 'Pending Review' }}</strong></p>
                                @if ($borrowing->approved_at)
                                    <p class="text-[11px] text-gray-400">{{ $borrowing->approved_at->format('M d, Y h:i A') }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="align-top text-xs">
                            <p class="font-medium text-charcoal">{{ $borrowing->purpose }}</p>
                            @if ($borrowing->remarks)
                                <p class="mt-1 text-[11px] text-gray-500">Note: {{ $borrowing->remarks }}</p>
                            @endif
                            @if ($borrowing->rejection_reason)
                                <p class="mt-1 font-semibold text-red-700">Rejected: {{ $borrowing->rejection_reason }}</p>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Borrowed Items Breakdown Table -->
    <div class="table-shell">
        <div class="flex items-center justify-between border-b border-gray-100 bg-[#FAF6F7]/70 px-6 py-4">
            <h2 class="text-sm font-bold text-charcoal">Borrowed Items Breakdown ({{ $borrowing->items->count() }})</h2>
        </div>

        <div class="table-scroll overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Property Code</th>
                        <th>Item Name</th>
                        <th class="text-center">Requested</th>
                        <th class="text-center">Approved</th>
                        <th class="text-center">Returned</th>
                        <th class="text-center">Remaining</th>
                        <th>Condition at Borrow</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($borrowing->items as $bi)
                        @php $remaining = $bi->remainingQuantity(); @endphp
                        <tr>
                            <td class="whitespace-nowrap">
                                <span class="inline-flex rounded-lg bg-maroon-50 px-2.5 py-1 font-mono text-[11px] font-bold text-maroon-900 ring-1 ring-maroon-200/70">
                                    {{ $bi->inventoryItem?->property_code }}
                                </span>
                            </td>
                            <td class="font-bold text-charcoal">
                                {{ $bi->inventoryItem?->name }}
                            </td>
                            <td class="whitespace-nowrap text-center font-semibold tabular-nums text-charcoal">
                                {{ $bi->quantity_requested }}
                            </td>
                            <td class="whitespace-nowrap text-center font-semibold tabular-nums text-charcoal">
                                {{ $bi->quantity_approved }}
                            </td>
                            <td class="whitespace-nowrap text-center font-semibold tabular-nums text-emerald-700">
                                {{ $bi->quantity_returned }}
                            </td>
                            <td class="whitespace-nowrap text-center font-bold tabular-nums {{ $remaining > 0 ? 'text-maroon-900' : 'text-emerald-700' }}">
                                {{ $remaining }}
                            </td>
                            <td class="whitespace-nowrap text-xs text-gray-600">
                                {{ $bi->conditionOnBorrow?->name ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap">
                                <x-status-badge :status="$bi->status" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Return & Inspection History Table -->
    <div class="table-shell">
        <div class="border-b border-gray-100 bg-[#FAF6F7]/70 px-6 py-4">
            <h2 class="text-sm font-bold text-charcoal">Return &amp; Inspection History ({{ $borrowing->returnTransactions->count() }})</h2>
        </div>

        @if ($borrowing->returnTransactions->isEmpty())
            <div class="p-6 text-center text-xs text-gray-500">No return transactions recorded yet.</div>
        @else
            <div class="table-scroll overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Return Code</th>
                            <th>Item &amp; Qty</th>
                            <th>Condition</th>
                            <th>Return Date</th>
                            <th>Returned / Received By</th>
                            <th>Remarks &amp; Inspection</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($borrowing->returnTransactions as $ret)
                            <tr>
                                <td class="align-top whitespace-nowrap">
                                    <span class="inline-flex rounded-lg bg-emerald-50 px-2.5 py-1 font-mono text-[11px] font-bold text-emerald-800 ring-1 ring-emerald-200/70">
                                        {{ $ret->return_code }}
                                    </span>
                                </td>
                                <td class="align-top whitespace-nowrap font-semibold text-charcoal">
                                    {{ $ret->quantity_returned }}x {{ $ret->inventoryItem?->name }}
                                </td>
                                <td class="align-top whitespace-nowrap">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <x-condition-badge :condition="$ret->conditionOnReturn" />
                                        @if ($ret->is_missing)
                                            <x-status-badge status="missing" label="Reported Missing" />
                                        @elseif ($ret->flagged_for_inspection)
                                            <x-status-badge :status="$ret->inspection_status" />
                                        @endif
                                    </div>
                                </td>
                                <td class="align-top whitespace-nowrap text-xs tabular-nums text-gray-700">
                                    {{ $ret->actual_return_date->format('M d, Y') }}
                                </td>
                                <td class="align-top whitespace-nowrap text-xs font-medium text-charcoal">
                                    {{ $ret->receiver?->name ?? 'N/A' }}
                                </td>
                                <td class="align-top text-xs">
                                    @if ($ret->remarks)
                                        <p class="text-gray-600">{{ $ret->remarks }}</p>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif

                                    @if ($ret->inspected_at)
                                        <p class="mt-1.5 rounded-lg bg-gray-50 px-3 py-1.5 text-[11px] text-gray-700">
                                            <strong>Inspection ({{ $ret->inspector?->name }}):</strong> {{ $ret->inspection_remarks }}
                                        </p>
                                    @endif

                                    @if ($ret->flagged_for_inspection && $ret->inspection_status === 'pending_inspection')
                                        @permission('borrowings.process_return')
                                            <form method="POST" action="{{ route('returns.inspect', $ret) }}" class="mt-2.5 grid gap-2 rounded-xl border border-orange-200 bg-orange-50/50 p-3 sm:grid-cols-4">
                                                @csrf
                                                <select name="resolution" required class="!py-1.5 !text-xs">
                                                    <option value="cleared_available">Clear &amp; Release to Available</option>
                                                    <option value="marked_damaged">Confirm Damaged (Hold)</option>
                                                    <option value="sent_maintenance">Send to Maintenance</option>
                                                    <option value="disposed">Dispose Unusable Units</option>
                                                </select>
                                                <select name="condition_id" class="!py-1.5 !text-xs">
                                                    @foreach ($conditions as $cond)
                                                        <option value="{{ $cond->id }}" @selected($ret->condition_on_return_id === $cond->id)>{{ $cond->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="inspection_remarks" required placeholder="Inspection findings..." class="!py-1.5 !text-xs">
                                                <button type="submit" class="rounded-lg bg-maroon-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-maroon-800">
                                                    Resolve Inspection
                                                </button>
                                            </form>
                                        @endpermission
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- Reject Modal -->
<x-modal name="reject-borrowing" title="Reject Borrowing Request" maxWidth="md">
    <form method="POST" action="{{ route('borrowings.reject', $borrowing) }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-semibold text-gray-700">Reason for Rejection *</label>
            <textarea name="rejection_reason" rows="3" required placeholder="Explain why this request cannot be approved..." class="mt-1"></textarea>
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" x-on:click="$dispatch('close-modal', 'reject-borrowing')" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600">Cancel</button>
            <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-xs font-semibold text-white hover:bg-red-800">Confirm Rejection</button>
        </div>
    </form>
</x-modal>
@endsection
