@extends('layouts.guest')

@section('title', 'Save Backup Codes — PCB-SSC SecureLocal')

@section('content')
<div class="flex min-h-screen flex-col justify-center bg-gradient-to-br from-maroon-950 via-maroon-900 to-maroon-700 px-4 py-10">
    <div class="mx-auto w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/10">
        <div class="bg-emerald-700 px-6 py-4 text-white">
            <p class="text-[11px] font-semibold tracking-wider text-emerald-100 uppercase">Two-Factor Authentication Enabled</p>
            <h1 class="text-base font-bold">Save Your One-Time Backup Codes</h1>
        </div>

        <div class="space-y-4 p-6 text-xs text-gray-700">
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-amber-900">
                <strong>Important:</strong> These 8 backup codes are stored encrypted/hashed and will <strong>only be displayed once</strong>. Save or print them in a safe place in case you lose access to your authenticator app.
            </div>

            <div class="grid grid-cols-2 gap-2.5 rounded-xl border border-gray-200 bg-gray-50 p-4 font-mono text-sm font-bold text-charcoal">
                @foreach ($backupCodes as $code)
                    <div class="rounded-lg bg-white px-3 py-2 text-center ring-1 ring-gray-200">{{ $code }}</div>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <button type="button" onclick="window.print()" class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                    Print Backup Codes
                </button>
                <a href="{{ route('dashboard') }}" class="rounded-xl bg-maroon-900 px-5 py-2.5 text-xs font-semibold text-white hover:bg-maroon-800">
                    I Saved My Codes — Continue &rarr;
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
