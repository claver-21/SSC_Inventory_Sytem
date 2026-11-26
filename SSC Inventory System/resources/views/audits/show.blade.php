@extends('layouts.app')

@section('page_title', $audit->audit_code . ' — ' . $audit->title)
@section('page_subtitle', 'Conducted by ' . $audit->conductor?->name . ' on ' . $audit->audit_date->format('M d, Y'))

@section('content')
<div class="space-y-6">
    <!-- Audit Header Card -->
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="rounded-lg bg-maroon-900 px-3 py-1 font-mono text-xs font-bold text-white">{{ $audit->audit_code }}</span>
                <x-status-badge :status="$audit->status" />
                <span class="text-xs text-gray-500">
                    Scope: {{ $audit->location?->name ?? 'All Locations' }} / {{ $audit->category?->name ?? 'All Categories' }}
                </span>
            </div>

            <div class="text-xs text-gray-500">
                @if ($audit->approved_at)
                    <span>Reviewed by <strong>{{ $audit->approver?->name }}</strong> on {{ $audit->approved_at->format('M d, Y h:i A') }}</span>
                @endif
            </div>
        </div>

        @if ($audit->approval_remarks)
            <div class="mt-3 rounded-xl bg-gray-50 px-4 py-2.5 text-xs text-gray-700">
                <strong>Approval Notes:</strong> {{ $audit->approval_remarks }}
            </div>
        @endif
    </div>

    <!-- Physical Count Workbench -->
    @if ($audit->isEditable() && auth()->user()->hasPermission('audits.conduct'))
        <form method="POST" action="{{ route('audits.counts.update', $audit) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 text-left text-[11px] font-semibold text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3 text-center">System Qty</th>
                                <th class="px-4 py-3 w-28 text-center">Physical Qty *</th>
                                <th class="px-4 py-3 text-center">Discrepancy</th>
                                <th class="px-4 py-3 w-24 text-center">Damaged</th>
                                <th class="px-4 py-3 w-24 text-center">Missing</th>
                                <th class="px-4 py-3">Observed Condition</th>
                                <th class="px-4 py-3">Audit Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($audit->items as $ai)
                                <tr x-data="{ sys: {{ $ai->system_quantity }}, phys: {{ $ai->physical_quantity ?? $ai->system_quantity }} }">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-charcoal">{{ $ai->inventoryItem?->name }}</p>
                                        <p class="font-mono text-[11px] text-gray-500">{{ $ai->inventoryItem?->property_code }} · {{ $ai->inventoryItem?->location?->name ?? 'Unassigned' }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center font-bold">{{ $ai->system_quantity }}</td>
                                    <td class="px-3 py-2">
                                        <input type="number" min="0" name="counts[{{ $ai->id }}][physical_quantity]" x-model.number="phys" required class="!py-1.5 !text-xs text-center font-bold">
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center font-bold">
                                        <span
                                            :class="(phys - sys) < 0 ? 'text-red-700 bg-red-50 px-2 py-0.5 rounded' : ((phys - sys) > 0 ? 'text-blue-700 bg-blue-50 px-2 py-0.5 rounded' : 'text-emerald-700')"
                                            x-text="(phys - sys) > 0 ? '+' + (phys - sys) : (phys - sys)"
                                        ></span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" min="0" name="counts[{{ $ai->id }}][damaged_quantity]" value="{{ $ai->damaged_quantity }}" class="!py-1.5 !text-xs text-center">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" min="0" name="counts[{{ $ai->id }}][missing_quantity]" value="{{ $ai->missing_quantity }}" class="!py-1.5 !text-xs text-center">
                                    </td>
                                    <td class="px-3 py-2">
                                        <select name="counts[{{ $ai->id }}][observed_condition_id]" class="!py-1.5 !text-xs">
                                            @foreach ($conditions as $cond)
                                                <option value="{{ $cond->id }}" @selected($ai->observed_condition_id === $cond->id)>{{ $cond->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="text" name="counts[{{ $ai->id }}][remarks]" value="{{ $ai->remarks }}" placeholder="Notes..." class="!py-1.5 !text-xs">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Overall Audit Remarks</label>
                    <textarea name="remarks" rows="2" class="mt-1">{{ $audit->remarks }}</textarea>
                </div>
                <div class="flex flex-wrap justify-end gap-3">
                    <button type="submit" name="action_type" value="save_draft" class="rounded-xl border border-maroon-200 bg-maroon-50 px-5 py-2.5 text-xs font-semibold text-maroon-900 hover:bg-maroon-100">
                        Save Draft Counts
                    </button>
                    <button type="submit" name="action_type" value="submit_approval" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-maroon-800">
                        Submit for Admin Approval
                    </button>
                </div>
            </div>
        </form>
    @else
        <!-- Preserved Read-Only Audit Record -->
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 text-left text-[11px] font-semibold text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3 text-center">System Snapshot</th>
                            <th class="px-4 py-3 text-center">Physical Count</th>
                            <th class="px-4 py-3 text-center">Discrepancy</th>
                            <th class="px-4 py-3 text-center">Damaged / Missing</th>
                            <th class="px-4 py-3">Observed Condition</th>
                            <th class="px-4 py-3">Reconciled</th>
                            <th class="px-4 py-3">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($audit->items as $ai)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-charcoal">{{ $ai->inventoryItem?->name }}</p>
                                    <p class="font-mono text-[11px] text-gray-500">{{ $ai->inventoryItem?->property_code }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center font-semibold">{{ $ai->system_quantity }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center font-bold">{{ $ai->physical_quantity }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center font-bold {{ $ai->discrepancy < 0 ? 'text-red-700' : ($ai->discrepancy > 0 ? 'text-blue-700' : 'text-emerald-700') }}">
                                    {{ $ai->discrepancy > 0 ? '+' . $ai->discrepancy : $ai->discrepancy }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">{{ $ai->damaged_quantity }} / {{ $ai->missing_quantity }}</td>
                                <td class="whitespace-nowrap px-4 py-3"><x-condition-badge :condition="$ai->observedCondition" /></td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ($ai->adjustment_applied)
                                        <span class="rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Adjusted</span>
                                    @else
                                        <span class="text-gray-400">No change</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $ai->remarks ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($audit->status === 'pending_approval')
            @permission('audits.approve')
                <div class="grid gap-4 rounded-2xl border border-maroon-200 bg-white p-5 shadow-xs sm:grid-cols-2">
                    <form method="POST" action="{{ route('audits.approve', $audit) }}" class="space-y-3">
                        @csrf
                        <h3 class="text-sm font-bold text-emerald-800">Approve &amp; Reconcile Inventory Adjustments</h3>
                        <p class="text-xs text-gray-600">Approving preserves the original audit counts and creates formal stock adjustment movements for any discrepancies.</p>
                        <input type="text" name="approval_remarks" placeholder="Optional approval notes..." class="!text-xs">
                        <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-semibold text-white hover:bg-emerald-700">
                            Approve Audit &amp; Apply Adjustments
                        </button>
                    </form>

                    <form method="POST" action="{{ route('audits.reject', $audit) }}" class="space-y-3 border-t border-gray-100 pt-4 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-5">
                        @csrf
                        <h3 class="text-sm font-bold text-red-800">Reject Audit Session</h3>
                        <p class="text-xs text-gray-600">Rejecting leaves inventory stock untouched and preserves the submitted audit record.</p>
                        <input type="text" name="approval_remarks" required placeholder="Reason for rejection *" class="!text-xs">
                        <button type="submit" class="rounded-xl border border-red-200 bg-red-50 px-5 py-2.5 text-xs font-semibold text-red-700 hover:bg-red-100">
                            Reject Audit
                        </button>
                    </form>
                </div>
            @endpermission
        @endif
    @endif
</div>
@endsection
