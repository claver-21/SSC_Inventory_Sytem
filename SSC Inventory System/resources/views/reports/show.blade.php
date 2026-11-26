@extends('layouts.app')

@section('page_title', $report['title'])
@section('page_subtitle', $report['subtitle'])

@section('content')
<div class="space-y-5">
    <!-- Filter & Export Toolbar -->
    <form method="GET" action="{{ route('reports.show', $type) }}" class="no-print rounded-2xl border border-gray-200 bg-white p-4 shadow-xs space-y-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="block text-[11px] font-semibold text-gray-600">Date From</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="mt-1">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600">Date To</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="mt-1">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600">Category</label>
                <select name="category_id" class="mt-1">
                    <option value="">All Categories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(($filters['category_id'] ?? '') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600">Location</label>
                <select name="location_id" class="mt-1">
                    <option value="">All Locations</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(($filters['location_id'] ?? '') == $loc->id)>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600">Item Status</label>
                <select name="status" class="mt-1">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600">Physical Condition</label>
                <select name="condition_id" class="mt-1">
                    <option value="">All Conditions</option>
                    @foreach ($conditions as $cond)
                        <option value="{{ $cond->id }}" @selected(($filters['condition_id'] ?? '') == $cond->id)>{{ $cond->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-600">Responsible Person</label>
                <select name="responsible_user_id" class="mt-1">
                    <option value="">All Officers</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected(($filters['responsible_user_id'] ?? '') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <a href="{{ route('reports.show', $type) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Reset</a>
                <button type="submit" class="flex-1 rounded-lg bg-maroon-900 px-4 py-2 text-xs font-semibold text-white hover:bg-maroon-800">Apply Filters</button>
            </div>
        </div>

        @permission('reports.export')
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3">
                <a href="{{ route('reports.index') }}" class="text-xs font-semibold text-gray-600 hover:text-maroon-900">&larr; Back to Reports Hub</a>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                        <x-icon name="print" class="w-4 h-4" />
                        Print
                    </button>
                    <a href="{{ route('reports.pdf', array_merge(['type' => $type], $filters)) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-maroon-900 px-3.5 py-2 text-xs font-semibold text-white hover:bg-maroon-800">
                        <x-icon name="download" class="w-4 h-4" />
                        Export PDF
                    </a>
                    <a href="{{ route('reports.excel', array_merge(['type' => $type], $filters)) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-700 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                        <x-icon name="download" class="w-4 h-4" />
                        Export Excel (.xlsx)
                    </a>
                    <a href="{{ route('reports.csv', array_merge(['type' => $type], $filters)) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                        <x-icon name="download" class="w-4 h-4" />
                        Export CSV
                    </a>
                </div>
            </div>
        @endpermission
    </form>

    <!-- Printable Official Document Sheet -->
    <div class="print-card rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
        <!-- Official Letterhead -->
        <div class="flex flex-col items-center justify-between gap-4 border-b-2 border-maroon-900 pb-4 sm:flex-row">
            <div class="flex items-center gap-4">
                <img src="{{ asset('images/pcb-ssc-logo.png') }}" alt="PCB SSC Logo" class="h-16 w-16 object-contain">
                <div>
                    <p class="text-sm font-extrabold tracking-wide text-maroon-900 uppercase">{{ $meta['institution_name'] }}</p>
                    <p class="text-base font-bold tracking-wide text-charcoal uppercase">{{ $meta['organization_name'] }}</p>
                    <p class="text-xs text-gray-500">{{ $meta['campus_address'] }}</p>
                </div>
            </div>
            <div class="text-left sm:text-right text-xs text-gray-600">
                <p class="font-bold text-maroon-900 uppercase">{{ $report['title'] }}</p>
                <p>Generated: {{ $meta['generated_at'] }}</p>
                <p>Generated by: {{ $meta['generated_by'] }}</p>
            </div>
        </div>

        @if (! empty($report['summary']))
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($report['summary'] as $label => $value)
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase">{{ $label }}</p>
                        <p class="mt-0.5 text-sm font-bold text-charcoal">{{ $value }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 border border-gray-200 text-xs">
                <thead class="bg-maroon-900 text-left text-[11px] font-semibold text-white uppercase">
                    <tr>
                        @foreach ($report['columns'] as $col)
                            <th class="px-3 py-2.5">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($report['rows'] as $row)
                        <tr class="even:bg-gray-50/50">
                            @foreach ($row as $cell)
                                <td class="px-3 py-2 text-gray-700">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($report['columns']) }}" class="px-4 py-8 text-center text-gray-500">
                                No matching database records found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
