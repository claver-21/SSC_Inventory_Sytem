@extends('layouts.app')

@section('page_title', 'Start Physical Inventory Audit')
@section('page_subtitle', 'Snapshot current system stock quantities and begin physical counting')

@section('content')
<form method="POST" action="{{ route('audits.store') }}" class="mx-auto max-w-2xl space-y-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
    @csrf

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="block text-xs font-semibold text-gray-700">Audit Code *</label>
            <input type="text" name="audit_code" value="{{ old('audit_code', $suggestedCode) }}" required class="mt-1 font-mono uppercase">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700">Audit Date *</label>
            <input type="date" name="audit_date" value="{{ old('audit_date', now()->format('Y-m-d')) }}" required class="mt-1">
        </div>
    </div>

    <div>
        <label class="block text-xs font-semibold text-gray-700">Audit Session Title *</label>
        <input type="text" name="title" value="{{ old('title') }}" required placeholder="e.g., Q4 2026 General Physical Inventory Audit" class="mt-1">
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="block text-xs font-semibold text-gray-700">Location Scope (Optional)</label>
            <select name="location_id" class="mt-1">
                <option value="">All Storage Locations</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}" @selected(old('location_id') == $loc->id)>{{ $loc->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700">Category Scope (Optional)</label>
            <select name="category_id" class="mt-1">
                <option value="">All Categories</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label class="block text-xs font-semibold text-gray-700">Audit Remarks</label>
        <textarea name="remarks" rows="2" class="mt-1">{{ old('remarks') }}</textarea>
    </div>

    <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
        <a href="{{ route('audits.index') }}" class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-xs font-semibold text-gray-700">Cancel</a>
        <button type="submit" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-maroon-800">
            Initialize Audit Snapshot
        </button>
    </div>
</form>
@endsection
