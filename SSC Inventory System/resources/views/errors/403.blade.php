@extends('layouts.app')

@section('page_title', '403 — Access Denied')
@section('page_subtitle', 'Your current SSC role does not authorize access to this resource')

@section('content')
<div class="mx-auto max-w-lg rounded-2xl border border-red-200 bg-white p-8 text-center shadow-xs">
    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-red-700">
        <x-icon name="shield" class="w-7 h-7" />
    </div>
    <h2 class="mt-4 text-lg font-bold text-charcoal">Access Denied (HTTP 403)</h2>
    <p class="mt-2 text-xs leading-relaxed text-gray-600">
        {{ $exception?->getMessage() ?: 'You do not have permission to access this route or record.' }}
        This access attempt has been recorded in the security audit log.
    </p>
    <div class="mt-6 flex justify-center gap-3">
        <a href="{{ route('dashboard') }}" class="rounded-xl bg-maroon-900 px-5 py-2.5 text-xs font-semibold text-white hover:bg-maroon-800">
            Return to Dashboard
        </a>
    </div>
</div>
@endsection
