@extends('layouts.app')

@section('page_title', 'Borrowing & Return Management')
@section('page_subtitle', $isOfficer ? 'Track your borrowing requests, return deadlines, and transaction history' : 'Review requests, process returns, monitor overdue loans, and inspect damaged items')

@section('content')
<div class="space-y-5">
    <!-- Top Tabs & CTA -->
    <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-center">
        <div class="flex flex-wrap gap-1.5 rounded-2xl border border-gray-200 bg-white p-1.5 shadow-xs">
            @php
                $tabs = [
                    'all' => 'All Requests',
                    'pending' => 'Pending',
                    'active' => 'Active Loans',
                    'overdue' => 'Overdue',
                    'returned' => 'Returned',
                    'rejected' => 'Rejected',
                ];
            @endphp
            @foreach ($tabs as $key => $label)
                <a
                    href="{{ route('borrowings.index', array_merge(request()->except('page'), ['tab' => $key])) }}"
                    class="rounded-xl px-3.5 py-1.5 text-xs font-semibold transition {{ $tab === $key ? 'bg-maroon-900 text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100 hover:text-charcoal' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
            @permission('borrowings.process_return')
                <a
                    href="{{ route('borrowings.index', ['tab' => 'inspections']) }}"
                    class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-semibold transition {{ $tab === 'inspections' ? 'bg-orange-700 text-white shadow-xs' : 'bg-orange-50 text-orange-800 hover:bg-orange-100' }}"
                >
                    Damaged Inspections
                    @if ($pendingInspections->isNotEmpty())
                        <span class="rounded-full bg-orange-700 px-1.5 py-0.5 text-[10px] text-white">{{ $pendingInspections->count() }}</span>
                    @endif
                </a>
            @endpermission
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('borrowings.index', ['tab' => 'history']) }}"
                class="{{ $tab === 'history' ? 'btn-maroon' : 'btn-outline' }}"
            >
                <x-icon name="logs" class="w-4 h-4" />
                History
            </a>
            @if ($tab === 'history' && $borrowings->total() > 0)
                <form method="POST" action="{{ route('borrowings.clear-history') }}" onsubmit="return confirm('Delete all completed/closed history records? Active loans will not be removed.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-4 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-100">
                        <x-icon name="trash" class="w-4 h-4" />
                        Clear History
                    </button>
                </form>
            @endif
            @unless (auth()->user()->isAdmin())
                @permission('borrowings.request')
                    <a href="{{ route('borrowings.create') }}" class="btn-maroon">
                        <x-icon name="plus" class="w-4 h-4" />
                        New Borrowing Request
                    </a>
                @endpermission
            @endunless
        </div>
    </div>

    @if ($tab === 'inspections' && auth()->user()->hasPermission('borrowings.process_return'))
        <div class="table-shell border-orange-200">
            <div class="border-b border-orange-100 bg-orange-50/70 px-5 py-4">
                <h2 class="text-sm font-bold text-orange-900">Returned Damaged Equipment Awaiting Inspection</h2>
                <p class="text-xs text-orange-700">These returned items were flagged for physical inspection before being released back to available stock.</p>
            </div>
            @if ($pendingInspections->isEmpty())
                <div class="p-8 text-center text-xs text-gray-500">All flagged returns have been inspected.</div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($pendingInspections as $ret)
                        <div class="p-5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span class="font-mono text-xs font-bold text-maroon-900">{{ $ret->return_code }}</span>
                                    <span class="mx-1.5 text-gray-300">|</span>
                                    <span class="text-xs font-semibold text-charcoal">{{ $ret->inventoryItem?->name }} ({{ $ret->inventoryItem?->property_code }})</span>
                                    <span class="ml-2 rounded-full bg-orange-50 px-2.5 py-0.5 text-[11px] font-semibold text-orange-800 ring-1 ring-orange-200">Qty: {{ $ret->quantity_returned }}</span>
                                </div>
                                <span class="text-[11px] text-gray-500">
                                    Loan {{ $ret->borrowingRequest?->transaction_code }} · Returned {{ $ret->actual_return_date->format('M d, Y') }}
                                </span>
                            </div>
                            <p class="mt-1.5 text-xs text-gray-600">
                                Condition reported: <strong>{{ $ret->conditionOnReturn?->name }}</strong> · Remarks: {{ $ret->remarks ?: 'None' }}
                            </p>

                            <form method="POST" action="{{ route('returns.inspect', $ret) }}" class="mt-3 grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3.5 sm:grid-cols-4">
                                @csrf
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600">Inspection Decision *</label>
                                    <select name="resolution" required class="mt-1 !py-1.5 !text-xs">
                                        <option value="cleared_available">Clear &amp; Release to Available</option>
                                        <option value="marked_damaged">Confirm Damaged (Hold)</option>
                                        <option value="sent_maintenance">Send to Maintenance</option>
                                        <option value="disposed">Dispose Unusable Units</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600">Updated Condition</label>
                                    <select name="condition_id" class="mt-1 !py-1.5 !text-xs">
                                        @foreach ($conditions as $cond)
                                            <option value="{{ $cond->id }}" @selected($ret->condition_on_return_id === $cond->id)>{{ $cond->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600">Inspection Findings *</label>
                                    <input type="text" name="inspection_remarks" required placeholder="Inspection findings..." class="mt-1 !py-1.5 !text-xs">
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" class="btn-maroon w-full !py-2">
                                        Complete Inspection
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <!-- Filter Form -->
        <form method="GET" action="{{ route('borrowings.index') }}" class="card-surface grid gap-3 p-4 sm:grid-cols-4">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Transaction code, borrower, school ID..." class="sm:col-span-2">
            <input type="date" name="date_from" value="{{ request('date_from') }}" title="Borrowed From">
            <div class="flex gap-2">
                <input type="date" name="date_to" value="{{ request('date_to') }}" title="Borrowed To" class="flex-1">
                <button type="submit" class="btn-maroon">Filter</button>
            </div>
        </form>

        @if ($borrowings->isEmpty())
            <x-empty-state
                title="No borrowing transactions found"
                message="No borrowing requests match your current view."
                icon="borrowing"
                :actionLabel="! auth()->user()->isAdmin() && auth()->user()->hasPermission('borrowings.request') ? 'Submit Borrowing Request' : null"
                :actionHref="! auth()->user()->isAdmin() && auth()->user()->hasPermission('borrowings.request') ? route('borrowings.create') : null"
            />
        @else
            <div class="table-shell">
                <div class="table-scroll overflow-x-auto">
                    @if ($tab === 'history')
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Transaction &amp; Purpose</th>
                                    <th>Borrower &amp; Requester</th>
                                    <th>Items &amp; Qty</th>
                                    <th>Schedule &amp; Approval</th>
                                    <th>Return Record</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($borrowings as $br)
                                    <tr>
                                        <td class="align-top">
                                            <a href="{{ route('borrowings.show', $br) }}" class="inline-flex rounded-lg bg-maroon-50 px-2.5 py-1 font-mono text-[11px] font-bold text-maroon-900 ring-1 ring-maroon-200/70 hover:bg-maroon-100">
                                                {{ $br->transaction_code }}
                                            </a>
                                            <p class="mt-1.5 max-w-52 text-xs font-medium text-charcoal">{{ $br->purpose }}</p>
                                            <p class="mt-0.5 text-[11px] text-gray-400">{{ $br->created_at?->format('M d, Y h:i A') }}</p>
                                        </td>
                                        <td class="align-top">
                                            <p class="font-bold text-charcoal">{{ $br->borrower_name }}</p>
                                            <p class="text-[11px] text-gray-500">{{ $br->school_id }} &middot; {{ $br->department_organization }}</p>
                                            <p class="mt-1 text-[11px] text-gray-600">
                                                Requested by: <span class="font-semibold text-charcoal">{{ $br->requester?->name ?? 'N/A' }}</span>
                                            </p>
                                        </td>
                                        <td class="align-top">
                                            <div class="space-y-1">
                                                @foreach ($br->items as $bi)
                                                    <div class="rounded-lg bg-gray-50 px-2.5 py-1 text-[11px] text-gray-700">
                                                        <span class="font-semibold text-charcoal">{{ $bi->inventoryItem?->name }}</span>
                                                        <span class="font-mono text-[10px] text-gray-400">({{ $bi->inventoryItem?->property_code }})</span>
                                                        <span class="block text-[10px] text-gray-500">
                                                            Req: {{ $bi->quantity_requested }} &middot; Ret: {{ $bi->quantity_returned }}
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="align-top whitespace-nowrap text-xs">
                                            <p class="font-medium text-charcoal tabular-nums">{{ $br->date_borrowed->format('M d') }} &rarr; {{ $br->expected_return_date->format('M d, Y') }}</p>
                                            @if ($br->approver)
                                                <p class="mt-1 text-[11px] text-gray-600">Reviewed by: <strong>{{ $br->approver->name }}</strong></p>
                                                @if ($br->approved_at)
                                                    <p class="text-[10px] text-gray-400">{{ $br->approved_at->format('M d, Y h:i A') }}</p>
                                                @endif
                                            @else
                                                <p class="mt-1 text-[11px] text-amber-700">Pending Review</p>
                                            @endif
                                            @if ($br->rejection_reason)
                                                <p class="mt-1 max-w-44 truncate text-[11px] text-red-700" title="{{ $br->rejection_reason }}">Rejected: {{ $br->rejection_reason }}</p>
                                            @endif
                                        </td>
                                        <td class="align-top text-xs">
                                            @if ($br->returnTransactions->isEmpty())
                                                <span class="text-xs text-gray-400">—</span>
                                            @else
                                                <div class="space-y-1.5">
                                                    @foreach ($br->returnTransactions as $ret)
                                                        <div class="rounded-lg border border-emerald-100 bg-emerald-50/50 px-2.5 py-1 text-[11px]">
                                                            <p class="font-mono font-bold text-emerald-800">{{ $ret->return_code }}</p>
                                                            <p class="text-gray-700">{{ $ret->quantity_returned }}x {{ $ret->inventoryItem?->name }}</p>
                                                            <p class="text-[10px] text-gray-500">
                                                                {{ $ret->actual_return_date->format('M d, Y') }} &middot; By {{ $ret->receiver?->name ?? 'N/A' }}
                                                            </p>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td class="align-top whitespace-nowrap">
                                            <x-status-badge :status="$br->status" />
                                        </td>
                                        <td class="align-top whitespace-nowrap text-right">
                                            <div class="inline-flex items-center justify-end gap-1.5">
                                                <a href="{{ route('borrowings.show', $br) }}" class="btn-outline !rounded-lg !px-2.5 !py-1 !text-[11px]">
                                                    View
                                                </a>
                                                @unless ($br->isActiveLoan())
                                                    <form method="POST" action="{{ route('borrowings.destroy', $br) }}" onsubmit="return confirm('Delete this history record?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700 transition hover:bg-red-100">
                                                            <x-icon name="trash" class="w-3.5 h-3.5" />
                                                            Delete
                                                        </button>
                                                    </form>
                                                @endunless
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <table class="data-table">
                        <thead>
                            <tr>
                                <th>Transaction</th>
                                <th>Borrower &amp; Org</th>
                                <th>Items Requested</th>
                                <th>Borrow / Expected Return</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($borrowings as $br)
                                <tr>
                                    <td class="whitespace-nowrap">
                                        <a href="{{ route('borrowings.show', $br) }}" class="inline-flex rounded-lg bg-maroon-50 px-2.5 py-1 font-mono text-[11px] font-bold text-maroon-900 ring-1 ring-maroon-200/70 hover:bg-maroon-100">
                                            {{ $br->transaction_code }}
                                        </a>
                                        <p class="mt-1 text-[11px] text-gray-400">Req: {{ $br->request_date->format('M d, Y') }}</p>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-maroon-50 text-[11px] font-bold text-maroon-900 ring-1 ring-maroon-200">
                                                {{ strtoupper(substr($br->borrower_name, 0, 2)) }}
                                            </span>
                                            <div>
                                                <p class="font-bold text-charcoal">{{ $br->borrower_name }}</p>
                                                <p class="text-[11px] text-gray-500">{{ $br->school_id }} · {{ $br->department_organization }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($br->items->take(3) as $bi)
                                                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700">
                                                    {{ $bi->quantity_requested }}x {{ $bi->inventoryItem?->name }}
                                                </span>
                                            @endforeach
                                            @if ($br->items->count() > 3)
                                                <span class="rounded-md bg-gray-50 px-2 py-0.5 text-[11px] text-gray-500">+{{ $br->items->count() - 3 }} more</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <p class="font-medium text-charcoal tabular-nums">{{ $br->date_borrowed->format('M d') }} &rarr; {{ $br->expected_return_date->format('M d, Y') }}</p>
                                        @if ($br->isOverdue())
                                            <span class="mt-1 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-800">
                                                {{ $br->overdue_days }} day(s) overdue
                                            </span>
                                        @elseif ($br->return_requested_at && $br->isActiveLoan())
                                            <span class="mt-1 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800 ring-1 ring-amber-200">
                                                Return notice sent
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <x-status-badge :status="$br->status" />
                                    </td>
                                    <td class="whitespace-nowrap text-right">
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            @if ($br->isActiveLoan() && ! auth()->user()->isAdmin() && $br->requester_user_id === auth()->id())
                                                <form method="POST" action="{{ route('borrowings.return-request', $br) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-[11px] font-semibold text-white shadow-xs transition hover:bg-emerald-700">
                                                        <x-icon name="restore" class="h-3.5 w-3.5" />
                                                        Return
                                                    </button>
                                                </form>
                                            @elseif ($br->isActiveLoan() && auth()->user()->isAdmin())
                                                <form method="POST" action="{{ route('borrowings.notify-return', $br) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-[11px] font-semibold text-amber-800 shadow-xs transition hover:bg-amber-100">
                                                        <x-icon name="bell" class="h-3.5 w-3.5" />
                                                        Notice to Return
                                                    </button>
                                                </form>
                                            @endif
                                            <a href="{{ route('borrowings.show', $br) }}" class="btn-soft !rounded-lg !px-3 !py-1.5 !text-[11px]">
                                                Manage / Details
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>

                <div class="border-t border-gray-100 bg-gray-50/40 px-5 py-3">
                    {{ $borrowings->links() }}
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
