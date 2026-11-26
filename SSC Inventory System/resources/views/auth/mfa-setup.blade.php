@extends('layouts.guest')

@section('title', 'Set Up Two-Factor Authentication — PCB-SSC SecureLocal')

@section('content')
<div class="flex min-h-screen flex-col justify-center bg-gradient-to-br from-maroon-950 via-maroon-900 to-maroon-700 px-4 py-10">
    <div class="mx-auto w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/10">
        <div class="bg-maroon-900 px-6 py-4 text-white">
            <p class="text-[11px] font-semibold tracking-wider text-maroon-200 uppercase">Step 1 of 2 — Account Protection</p>
            <h1 class="text-base font-bold">Set Up Authenticator App (TOTP MFA)</h1>
        </div>

        <div class="space-y-5 p-6 text-xs text-gray-700">
            <p>
                Scan this QR code using <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong>, or any TOTP app for <strong>{{ $user->email }}</strong>.
            </p>

            <div class="flex flex-col items-center rounded-xl border border-gray-200 bg-gray-50 p-4">
                <canvas id="mfa-qr-canvas" class="rounded-lg bg-white p-2 shadow-xs"></canvas>
                <p class="mt-3 text-[11px] text-gray-500">Or enter this secret key manually:</p>
                <code class="mt-1 rounded bg-white px-3 py-1 font-mono text-xs font-bold tracking-widest text-maroon-900 ring-1 ring-gray-200">{{ $secret }}</code>
            </div>

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-3.5 py-2.5 text-xs text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('mfa.setup.confirm') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Enter 6-Digit Authenticator Code *</label>
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus placeholder="000000" class="mt-1 text-center font-mono text-lg tracking-widest">
                </div>

                <button type="submit" class="w-full rounded-xl bg-maroon-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-maroon-800">
                    Verify &amp; Activate 2FA
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('mfa-qr-canvas');
    if (canvas && window.QRCode) {
        window.QRCode.toCanvas(canvas, @json($otpAuthUri), { width: 180, margin: 1 });
    }
});
</script>
@endsection
