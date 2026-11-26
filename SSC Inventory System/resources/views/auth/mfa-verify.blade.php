@extends('layouts.guest')

@section('title', '2FA Verification — PCB-SSC SecureLocal')

@section('content')
<div class="flex min-h-screen flex-col justify-center bg-gradient-to-br from-maroon-950 via-maroon-900 to-maroon-700 px-4 py-10">
    <div class="mx-auto w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/10">
        <div class="bg-maroon-900 px-6 py-4 text-white">
            <p class="text-[11px] font-semibold tracking-wider text-maroon-200 uppercase">Two-Factor Authentication</p>
            <h1 class="text-base font-bold">Verify Your Identity</h1>
        </div>

        <div class="space-y-5 p-6 text-xs text-gray-700">
            <p>
                Enter the 6-digit code from your authenticator app or one of your single-use backup codes for <strong>{{ $user->email }}</strong>.
            </p>

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-3.5 py-2.5 text-xs text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('mfa.verify.attempt') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-700">6-Digit Code or Backup Code *</label>
                    <input type="text" name="code" required autofocus autocomplete="one-time-code" placeholder="123456 or XXXX-XXXX" class="mt-1 text-center font-mono text-base tracking-widest">
                </div>

                <button type="submit" class="w-full rounded-xl bg-maroon-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-maroon-800">
                    Verify &amp; Sign In
                </button>
            </form>

            <div class="border-t border-gray-100 pt-3 text-center">
                <a href="{{ route('login') }}" class="text-xs font-semibold text-gray-500 hover:text-maroon-900">
                    Use a different account
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
