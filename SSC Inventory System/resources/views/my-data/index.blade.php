@extends('layouts.app')

@section('page_title', 'My Data & Privacy (RA 10173)')
@section('page_subtitle', 'Review your stored SSC account data, privacy consent record, 2FA protection, and borrowing history')

@section('content')
<div class="space-y-6">
    <!-- Top Banner & Export CTA -->
    <div class="flex flex-col justify-between gap-4 rounded-2xl border border-maroon-200 bg-white p-5 shadow-xs sm:flex-row sm:items-center">
        <div>
            <h2 class="text-sm font-bold text-charcoal">Data Subject Rights &amp; Transparency</h2>
            <p class="mt-0.5 text-xs text-gray-600">
                Under the Data Privacy Act of 2012 (RA 10173), you can inspect all council records stored under your account and download a portable JSON copy.
            </p>
        </div>
        <a href="{{ route('my-data.export') }}" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-maroon-900 px-4 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-maroon-800">
            <x-icon name="download" class="w-4 h-4" />
            Download My Data (JSON)
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <!-- Profile & Consent Summary -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h3 class="text-sm font-bold text-charcoal">Stored Personal &amp; Council Profile</h3>
                <a href="{{ route('profile.edit') }}" class="text-xs font-semibold text-maroon-800 hover:underline">Edit Profile &rarr;</a>
            </div>
            <dl class="mt-4 grid gap-3 text-xs sm:grid-cols-2">
                <div>
                    <dt class="text-[11px] font-semibold text-gray-500 uppercase">Full Name</dt>
                    <dd class="mt-0.5 font-semibold text-charcoal">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold text-gray-500 uppercase">Institutional Email</dt>
                    <dd class="mt-0.5 font-semibold text-charcoal">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold text-gray-500 uppercase">PCB Student ID</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-charcoal">{{ $user->school_id ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold text-gray-500 uppercase">Assigned Role &amp; Position</dt>
                    <dd class="mt-0.5 text-charcoal">{{ $user->role?->name }} · {{ $user->position ?: 'Member' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold text-gray-500 uppercase">RA 10173 Privacy Consent</dt>
                    <dd class="mt-0.5 font-semibold text-emerald-700">
                        @if ($user->privacy_consent_at)
                            Accepted ({{ $user->privacy_consent_version ?? 'v1.0' }}) on {{ $user->privacy_consent_at->format('M d, Y h:i A') }}
                        @else
                            Pending confirmation
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold text-gray-500 uppercase">Last Sign-In</dt>
                    <dd class="mt-0.5 text-charcoal">{{ $user->last_login_at?->format('M d, Y h:i A') ?? 'Current session' }}</dd>
                </div>
            </dl>
        </div>

        <!-- MFA & Backup Code Status -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-sm font-bold text-charcoal">Two-Factor Authentication (2FA)</h3>
                    <x-status-badge :status="$user->mfa_enabled ? 'active' : 'inactive'" :label="$user->mfa_enabled ? 'TOTP Enabled' : 'Not Configured'" />
                </div>
                <div class="mt-4 space-y-2 text-xs text-gray-600">
                    <p><strong>Activated At:</strong> {{ $user->mfa_confirmed_at?->format('M d, Y h:i A') ?? '—' }}</p>
                    <p><strong>Remaining Unused Backup Codes:</strong> <span class="font-bold text-charcoal">{{ $unusedBackupCodesCount }}</span> of 8 (stored hashed via Bcrypt)</p>
                    <p class="text-[11px] text-gray-500">Regenerating backup codes requires Step-Up MFA verification and invalidates previous unused codes.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('mfa.backup-codes.regenerate') }}" class="mt-4 border-t border-gray-100 pt-3">
                @csrf
                <button type="submit" class="rounded-xl border border-maroon-200 bg-maroon-50 px-4 py-2 text-xs font-semibold text-maroon-900 hover:bg-maroon-100">
                    Regenerate Backup Codes (Step-Up MFA)
                </button>
            </form>
        </div>
    </div>

    <!-- Own Borrowing & Incident Summary -->
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs">
            <div class="border-b border-gray-100 px-5 py-3.5 text-sm font-bold text-charcoal">
                My Borrowing History ({{ $borrowings->count() }})
            </div>
            @if ($borrowings->isEmpty())
                <div class="p-6 text-center text-xs text-gray-500">No borrowing transactions submitted yet.</div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($borrowings as $b)
                        <a href="{{ route('borrowings.show', $b) }}" class="flex items-center justify-between px-5 py-3 text-xs hover:bg-maroon-50/30">
                            <div>
                                <p class="font-mono font-bold text-maroon-900">{{ $b->transaction_code }}</p>
                                <p class="text-[11px] text-gray-500">{{ $b->purpose }} · Due {{ $b->expected_return_date->format('M d, Y') }}</p>
                            </div>
                            <x-status-badge :status="$b->status" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs">
            <div class="border-b border-gray-100 px-5 py-3.5 text-sm font-bold text-charcoal">
                My Submitted Incidents ({{ $incidents->count() }})
            </div>
            @if ($incidents->isEmpty())
                <div class="p-6 text-center text-xs text-gray-500">No incidents submitted yet.</div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($incidents as $inc)
                        <div class="flex items-center justify-between px-5 py-3 text-xs">
                            <div>
                                <p class="font-mono font-bold text-maroon-900">{{ $inc->report_code }}</p>
                                <p class="text-[11px] text-gray-500">{{ ucfirst(str_replace('_', ' ', $inc->incident_type)) }} — {{ $inc->created_at?->format('M d, Y') }}</p>
                            </div>
                            <x-status-badge :status="$inc->status" />
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
