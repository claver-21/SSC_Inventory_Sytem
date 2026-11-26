@extends('layouts.app')

@section('page_title', 'Physical Inventory Audits')
@section('page_subtitle', 'Conduct physical counts, compare against system stock, and reconcile discrepancies')

@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('audits.index') }}" class="flex items-center gap-2">
            <select name="status" class="w-48">
                <option value="">All Audit Statuses</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Filter</button>
        </form>

        @permission('audits.conduct')
            <a href="{{ route('audits.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-maroon-900 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-maroon-800">
                <x-icon name="plus" class="w-4 h-4" />
                Start Audit Session
            </a>
        @endpermission
    </div>

    @if ($audits->isEmpty())
        <x-empty-state
            title="No inventory audit sessions"
            message="Create a physical inventory audit session to snapshot system quantities and record verified physical counts."
            icon="audit"
            :actionLabel="auth()->user()->hasPermission('audits.conduct') ? 'Create Audit Session' : null"
            :actionHref="auth()->user()->hasPermission('audits.conduct') ? route('audits.create') : null"
        />
    @else
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 text-left text-[11px] font-semibold text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-3">Audit Code</th>
                            <th class="px-4 py-3">Title &amp; Scope</th>
                            <th class="px-4 py-3">Audit Date</th>
                            <th class="px-4 py-3 text-center">Items</th>
                            <th class="px-4 py-3 text-center">Discrepancies</th>
                            <th class="px-4 py-3">Conductor / Approver</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($audits as $audit)
                            <tr class="hover:bg-maroon-50/30">
                                <td class="whitespace-nowrap px-4 py-3 font-mono font-bold text-maroon-900">
                                    <a href="{{ route('audits.show', $audit) }}" class="hover:underline">{{ $audit->audit_code }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-charcoal">{{ $audit->title }}</p>
                                    <p class="text-[11px] text-gray-500">
                                        {{ $audit->location?->name ?? 'All Locations' }} · {{ $audit->category?->name ?? 'All Categories' }}
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $audit->audit_date->format('M d, Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center font-semibold">{{ $audit->items_count }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    <span class="rounded-full px-2 py-0.5 font-semibold {{ $audit->discrepancy_items_count > 0 ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">
                                        {{ $audit->discrepancy_items_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <p>By: {{ $audit->conductor?->name }}</p>
                                    <p class="text-[11px] text-gray-400">Approved: {{ $audit->approver?->name ?? 'Pending' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3"><x-status-badge :status="$audit->status" /></td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <a href="{{ route('audits.show', $audit) }}" class="rounded-lg border border-maroon-200 bg-maroon-50 px-3 py-1.5 text-[11px] font-semibold text-maroon-900 hover:bg-maroon-100">
                                        Open Audit
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-3">{{ $audits->links() }}</div>
        </div>
    @endif
</div>
@endsection
