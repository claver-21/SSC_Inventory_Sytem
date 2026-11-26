@extends('layouts.app')

@section('page_title', 'System Audit Logs')
@section('page_subtitle', 'Immutable chronological accountability trail for all system actions and security events')

@section('content')
<div class="space-y-5" x-data="{ selectedLog: null }">
    @if ($securityMetrics['spike_detected'])
        <div class="flex items-center gap-3 rounded-2xl border border-red-200 bg-gradient-to-r from-red-50 to-rose-50 px-5 py-4 text-xs text-red-900 shadow-xs">
            <x-icon name="alert" class="w-5 h-5 shrink-0 text-red-700" />
            <div>
                <p class="font-bold">Security Alert: Failed Login Spike Detected</p>
                <p>{{ $securityMetrics['failed_logins_24h'] }} failed login or lockout events occurred in the last 24 hours. Review suspicious IPs and locked accounts.</p>
            </div>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-4">
        <div class="card-surface p-4 text-xs">
            <p class="font-bold tracking-wider text-gray-500 uppercase text-[11px]">Failed Logins (24h)</p>
            <p class="mt-1.5 text-2xl font-extrabold tabular-nums {{ $securityMetrics['spike_detected'] ? 'text-red-700' : 'text-charcoal' }}">{{ $securityMetrics['failed_logins_24h'] }}</p>
        </div>
        <div class="card-surface p-4 text-xs">
            <p class="font-bold tracking-wider text-gray-500 uppercase text-[11px]">Locked Accounts</p>
            <p class="mt-1.5 text-2xl font-extrabold tabular-nums {{ $securityMetrics['locked_accounts'] > 0 ? 'text-red-700' : 'text-charcoal' }}">{{ $securityMetrics['locked_accounts'] }}</p>
        </div>
        <div class="card-surface p-4 text-xs">
            <p class="font-bold tracking-wider text-gray-500 uppercase text-[11px]">Access Denied (24h)</p>
            <p class="mt-1.5 text-2xl font-extrabold tabular-nums text-charcoal">{{ $securityMetrics['access_denied_24h'] }}</p>
        </div>
        <div class="card-surface p-4 text-xs">
            <p class="font-bold tracking-wider text-gray-500 uppercase text-[11px]">Step-Up MFA (24h)</p>
            <p class="mt-1.5 text-2xl font-extrabold tabular-nums text-charcoal">{{ $securityMetrics['step_up_24h'] }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('audit-logs.index') }}" class="card-surface grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Action, record, IP, user, description..." class="lg:col-span-2">
        <select name="user_id">
            <option value="">All Users</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        <select name="module">
            <option value="">All Modules</option>
            @foreach ($modules as $mod)
                <option value="{{ $mod }}" @selected(request('module') === $mod)>{{ ucfirst(str_replace('_', ' ', $mod)) }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" title="From Date">
        <div class="flex gap-2">
            <input type="date" name="date_to" value="{{ request('date_to') }}" title="To Date" class="flex-1">
            <button type="submit" class="btn-maroon">Filter</button>
        </div>
    </form>

    @if ($logs->isEmpty())
        <x-empty-state title="No audit logs found" message="System activity logs matching your filter will appear here." icon="logs" />
    @else
        <div class="table-shell">
            <div class="table-scroll overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Module</th>
                            <th>Action</th>
                            <th>Record</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th class="text-right">Diff</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap text-gray-500 tabular-nums">{{ $log->created_at?->format('M d, Y H:i:s') }}</td>
                                <td class="whitespace-nowrap font-bold text-charcoal">{{ $log->user_name }}</td>
                                <td class="whitespace-nowrap">
                                    <span class="rounded-lg bg-maroon-50 px-2.5 py-0.5 text-[11px] font-semibold text-maroon-900 ring-1 ring-maroon-200/70">
                                        {{ ucfirst(str_replace('_', ' ', $log->module)) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap">
                                    <code class="rounded bg-gray-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-gray-800">{{ $log->action }}</code>
                                </td>
                                <td class="whitespace-nowrap font-semibold text-charcoal">{{ $log->record_label ?? '—' }}</td>
                                <td class="text-gray-600">{{ $log->description }}</td>
                                <td class="whitespace-nowrap font-mono text-[11px] text-gray-500">{{ $log->ip_address ?? '—' }}</td>
                                <td class="whitespace-nowrap text-right">
                                    @if ($log->old_values || $log->new_values)
                                        <button
                                            type="button"
                                            x-on:click="selectedLog = {{ Js::from($log) }}; $dispatch('open-modal', 'audit-diff')"
                                            class="btn-soft !rounded-lg !px-2.5 !py-1 !text-[11px]"
                                        >
                                            Inspect
                                        </button>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 bg-gray-50/40 px-5 py-3">{{ $logs->links() }}</div>
        </div>
    @endif

    <x-modal name="audit-diff" title="Audit Record Change Comparison" maxWidth="2xl">
        <template x-if="selectedLog">
            <div class="space-y-4 text-xs">
                <div class="rounded-xl bg-gray-50 p-3">
                    <p><strong>Action:</strong> <span class="font-mono" x-text="selectedLog.action"></span></p>
                    <p><strong>Actor:</strong> <span x-text="selectedLog.user_name"></span> · <strong>IP:</strong> <span class="font-mono" x-text="selectedLog.ip_address || 'N/A'"></span></p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="mb-1 font-bold text-gray-700">Previous Values</p>
                        <pre class="max-h-64 overflow-auto rounded-xl border border-gray-200 bg-gray-50 p-3 text-[11px]" x-text="JSON.stringify(selectedLog.old_values, null, 2)"></pre>
                    </div>
                    <div>
                        <p class="mb-1 font-bold text-maroon-900">Updated Values</p>
                        <pre class="max-h-64 overflow-auto rounded-xl border border-maroon-200 bg-maroon-50/40 p-3 text-[11px]" x-text="JSON.stringify(selectedLog.new_values, null, 2)"></pre>
                    </div>
                </div>
            </div>
        </template>
    </x-modal>
</div>
@endsection
