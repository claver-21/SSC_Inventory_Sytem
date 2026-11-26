@extends('layouts.guest')

@section('title', 'Sign In — PCB-SSC SecureLocal Inventory')

@section('content')
<div class="flex min-h-screen flex-col justify-center bg-white px-4 py-10 sm:px-6 lg:px-8">
    <div class="mx-auto w-full max-w-4xl overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-xl md:grid md:grid-cols-12">
        <!-- Institutional Maroon Left Panel -->
        <div class="flex flex-col items-center justify-center bg-maroon-900 px-8 py-10 text-center text-white md:col-span-5">
            <img
                src="{{ asset('images/pcb-ssc-logo.png') }}"
                alt="Polytechnic College of Botolan Supreme Student Council Seal"
                class="h-28 w-28 rounded-full bg-white p-1.5 object-contain shadow-lg ring-4 ring-white/20"
            >
            <p class="mt-5 text-xs font-semibold tracking-widest text-maroon-200 uppercase">
                Polytechnic College of Botolan
            </p>
            <h1 class="mt-1 text-xl font-bold tracking-tight text-white">
                Supreme Student Council
            </h1>
            <div class="my-4 h-0.5 w-16 rounded-full bg-rose-accent/60"></div>
            <p class="text-xs leading-relaxed text-maroon-100/90">
                SecureLocal Inventory &amp; Borrow/Return Portal — Restricted strictly to authorized PCB-SSC officers and student members.
            </p>
            <span class="mt-5 inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-[11px] font-medium text-maroon-100 ring-1 ring-white/15">
                <x-icon name="shield" class="w-3.5 h-3.5" />
                {{ config('auth.mfa_enabled', true) ? '2FA & Role-Protected Access' : 'Role-Protected SSC Access' }}
            </span>
        </div>

        <!-- Login Form Panel -->
        <div class="px-7 py-10 sm:px-10 md:col-span-7">
            <div>
                <h2 class="text-xl font-bold tracking-tight text-charcoal">SSC Member Sign In</h2>
                <p class="mt-1 text-xs text-gray-500">
                    @if (config('auth.mfa_enabled', true))
                        Sign in with your personal PCB-SSC account (<code class="font-mono text-maroon-900">@pcb-ssc.edu.ph</code>). Never share passwords or 2FA codes.
                    @else
                        Sign in with your personal PCB-SSC account (<code class="font-mono text-maroon-900">@pcb-ssc.edu.ph</code>).
                    @endif
                </p>
            </div>

            @if (session('success'))
                <div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs font-medium text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-3.5 py-2.5 text-xs text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-semibold text-gray-700">SSC Institutional Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="member@pcb-ssc.edu.ph"
                        class="mt-1.5"
                    >
                </div>

                <div x-data="{ show: false }">
                    <label for="password" class="block text-xs font-semibold text-gray-700">Password</label>
                    <div class="relative mt-1.5">
                        <input
                            id="password"
                            name="password"
                            :type="show ? 'text' : 'password'"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your 12+ character password"
                            class="!pr-10"
                        >
                        <button
                            type="button"
                            x-on:click="show = !show"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition hover:text-gray-600"
                            aria-label="Toggle password visibility"
                        >
                            <span x-show="!show"><x-icon name="eye" class="h-4 w-4" /></span>
                            <span x-show="show" x-cloak><x-icon name="eye-off" class="h-4 w-4" /></span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                        <input type="checkbox" name="remember" value="1">
                        <span>Keep me signed in</span>
                    </label>
                    <span class="text-[11px] text-gray-400">5-attempt lockout active</span>
                </div>

                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-maroon-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-maroon-800 focus:outline-none focus:ring-2 focus:ring-maroon-700 focus:ring-offset-2"
                >
                    <x-icon name="shield" class="w-4 h-4" />
                    {{ config('auth.mfa_enabled', true) ? 'Continue to 2FA Verification' : 'Sign In' }}
                </button>
            </form>

            <div class="mt-6 border-t border-gray-100 pt-5 text-center text-xs text-gray-500">
                Need an SSC account? Account creation is managed exclusively by the <strong class="text-maroon-900">PCB-SSC Administrator</strong>.
            </div>
        </div>
    </div>
</div>
@endsection
