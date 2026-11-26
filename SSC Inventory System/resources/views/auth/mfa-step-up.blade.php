@extends('layouts.app')

@section('page_title', 'Step-Up MFA Verification')
@section('page_subtitle', 'Fresh authenticator verification required for sensitive inventory or security actions')

@section('content')
<div class="mx-auto max-w-md rounded-2xl border border-amber-200 bg-white p-6 shadow-xs">
    <div class="flex items-center gap-3 border-b border-gray-100 pb-4">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
            <x-icon name="shield" class="w-5 h-5" />
        </div>
        <div>
            <h2 class="text-sm font-bold text-charcoal">Confirm Sensitive Action</h2>
            <p class="text-xs text-gray-500">Valid for 5 minutes after confirmation</p>
        </div>
    </div>

    <p class="mt-4 text-xs leading-relaxed text-gray-600">
        Actions such as archiving/deleting inventory items, modifying user roles or accounts, changing security settings, or downloading SQL backups require fresh 2FA verification.
    </p>

    <form method="POST" action="{{ route('mfa.step-up.verify') }}" class="mt-5 space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-semibold text-gray-700">Enter 6-Digit Authenticator Code or Backup Code *</label>
            <input type="text" name="code" required autofocus autocomplete="one-time-code" placeholder="123456 or XXXX-XXXX" class="mt-1 text-center font-mono text-base tracking-widest">
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('dashboard') }}" class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600">Cancel</a>
            <button type="submit" class="rounded-xl bg-maroon-900 px-5 py-2 text-xs font-semibold text-white hover:bg-maroon-800">
                Confirm &amp; Proceed
            </button>
        </div>
    </form>
</div>
@endsection
