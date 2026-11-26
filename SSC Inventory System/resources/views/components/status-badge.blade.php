@props(['status', 'label' => null])

@php
    $normalized = strtolower((string) $status);
    $map = [
        'available' => ['label' => 'Available', 'classes' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'borrowed' => ['label' => 'Borrowed', 'classes' => 'bg-blue-50 text-blue-700 ring-blue-200'],
        'reserved' => ['label' => 'Reserved', 'classes' => 'bg-indigo-50 text-indigo-700 ring-indigo-200'],
        'under_maintenance' => ['label' => 'Under Maintenance', 'classes' => 'bg-amber-50 text-amber-800 ring-amber-200'],
        'damaged' => ['label' => 'Damaged', 'classes' => 'bg-orange-50 text-orange-800 ring-orange-200'],
        'missing' => ['label' => 'Missing', 'classes' => 'bg-red-50 text-red-700 ring-red-200'],
        'disposed' => ['label' => 'Disposed', 'classes' => 'bg-gray-100 text-gray-600 ring-gray-300'],
        'pending' => ['label' => 'Pending Approval', 'classes' => 'bg-amber-50 text-amber-800 ring-amber-200'],
        'approved' => ['label' => 'Approved / Active', 'classes' => 'bg-blue-50 text-blue-700 ring-blue-200'],
        'partially_returned' => ['label' => 'Partially Returned', 'classes' => 'bg-cyan-50 text-cyan-800 ring-cyan-200'],
        'returned' => ['label' => 'Returned', 'classes' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'overdue' => ['label' => 'Overdue', 'classes' => 'bg-red-100 text-red-800 ring-red-300'],
        'rejected' => ['label' => 'Rejected', 'classes' => 'bg-rose-50 text-rose-800 ring-rose-200'],
        'cancelled' => ['label' => 'Cancelled', 'classes' => 'bg-gray-100 text-gray-600 ring-gray-300'],
        'draft' => ['label' => 'Draft', 'classes' => 'bg-slate-100 text-slate-700 ring-slate-300'],
        'in_progress' => ['label' => 'In Progress', 'classes' => 'bg-blue-50 text-blue-700 ring-blue-200'],
        'pending_approval' => ['label' => 'Pending Approval', 'classes' => 'bg-amber-50 text-amber-800 ring-amber-200'],
        'pending_inspection' => ['label' => 'Pending Inspection', 'classes' => 'bg-orange-50 text-orange-800 ring-orange-200'],
        'cleared_available' => ['label' => 'Cleared to Available', 'classes' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'marked_damaged' => ['label' => 'Marked Damaged', 'classes' => 'bg-orange-50 text-orange-800 ring-orange-200'],
        'sent_maintenance' => ['label' => 'Sent to Maintenance', 'classes' => 'bg-amber-50 text-amber-800 ring-amber-200'],
        'open' => ['label' => 'Open', 'classes' => 'bg-red-50 text-red-700 ring-red-200'],
        'under_review' => ['label' => 'Under Review', 'classes' => 'bg-amber-50 text-amber-800 ring-amber-200'],
        'reported' => ['label' => 'Reported', 'classes' => 'bg-red-50 text-red-700 ring-red-200'],
        'investigating' => ['label' => 'Investigating', 'classes' => 'bg-amber-50 text-amber-800 ring-amber-200'],
        'contained' => ['label' => 'Contained', 'classes' => 'bg-blue-50 text-blue-700 ring-blue-200'],
        'resolved' => ['label' => 'Resolved', 'classes' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'lessons_learned' => ['label' => 'Lessons Learned', 'classes' => 'bg-indigo-50 text-indigo-700 ring-indigo-200'],
        'active' => ['label' => 'Active', 'classes' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'inactive' => ['label' => 'Inactive', 'classes' => 'bg-gray-100 text-gray-600 ring-gray-300'],
        'archived' => ['label' => 'Archived', 'classes' => 'bg-gray-100 text-gray-600 ring-gray-300'],
    ];

    $meta = $map[$normalized] ?? [
        'label' => ucfirst(str_replace('_', ' ', $normalized)),
        'classes' => 'bg-gray-100 text-gray-700 ring-gray-300',
    ];
    $display = $label ?? $meta['label'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset ' . $meta['classes']]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-85"></span>
    {{ $display }}
</span>
