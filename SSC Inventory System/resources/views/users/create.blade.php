@extends('layouts.app')

@section('page_title', 'Add New Account')
@section('page_subtitle', 'Provision a new Admin or SSC Student account for PCB-SSC')

@section('content')
<div
    class="mx-auto max-w-2xl"
    x-data="{
        firstName: @js(old('first_name', '')),
        lastName: @js(old('last_name', '')),
        email: @js(old('email', '')),
        emailTouched: {{ old('email') ? 'true' : 'false' }},
        schoolId: @js(old('school_id', '')),
        sex: @js(old('sex', 'Male')),
        roleId: @js(old('role_id', $roles->firstWhere('slug', 'member')?->id ?? $roles->first()?->id)),
        position: @js(old('position', 'SSC Member')),
        password: '',
        showPassword: false,
        domain: @js($allowedDomain),
        updateGeneratedEmail() {
            if (this.emailTouched) return;
            const slug = (this.firstName + ' ' + this.lastName).toLowerCase().replace(/[^a-z0-9]/g, '');
            this.email = slug ? `${slug}@${this.domain}` : '';
        },
        generateDefaultFormula() {
            const rawLetters = (this.firstName + ' ' + this.lastName).replace(/[^a-zA-Z]/g, '').toLowerCase();
            const base = rawLetters ? rawLetters.charAt(0).toUpperCase() + rawLetters.slice(1) : 'Pcbstudent';
            const idText = (this.schoolId || '').trim();
            const afterDash = idText.includes('-') ? idText.split('-').pop() : idText;
            const digits = (afterDash || '').replace(/\D/g, '') || idText.replace(/\D/g, '') || '2026';
            let pw = base + digits;
            while (pw.length < 12) {
                pw += '2026';
            }
            return pw;
        },
        fillDefaultPassword() {
            this.password = this.generateDefaultFormula();
            this.showPassword = true;
        },
        defaultHint() {
            return (this.firstName || this.lastName || this.schoolId) ? this.generateDefaultFormula() : 'Juandelacruz00182';
        }
    }"
>
    <form method="POST" action="{{ route('users.store') }}" class="card-surface space-y-5 p-6">
        @csrf

        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-rose-50 text-maroon-900">
                    <x-icon name="user-plus" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="text-base font-bold text-charcoal">Add New Account</h2>
                    <p class="text-xs text-gray-500">Institutional SSC email ({{ '@' . $allowedDomain }}) required</p>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-semibold text-charcoal">First Name *</label>
                <input
                    type="text"
                    name="first_name"
                    x-model="firstName"
                    x-on:input="updateGeneratedEmail()"
                    required
                    placeholder="e.g. Juan"
                    class="mt-1.5 !rounded-xl"
                >
            </div>
            <div>
                <label class="block text-xs font-semibold text-charcoal">Last Name *</label>
                <input
                    type="text"
                    name="last_name"
                    x-model="lastName"
                    x-on:input="updateGeneratedEmail()"
                    required
                    placeholder="e.g. Dela Cruz"
                    class="mt-1.5 !rounded-xl"
                >
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between gap-2">
                <label class="block text-xs font-semibold text-charcoal">SSC Email *</label>
                <span class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-[11px] font-medium text-gray-600">
                    <x-icon name="sparkles" class="h-3 w-3 text-sky-500" />
                    Auto-generates from name
                </span>
            </div>
            <input
                type="email"
                name="email"
                x-model="email"
                x-on:input="emailTouched = true"
                required
                placeholder="user@{{ $allowedDomain }}"
                class="mt-1.5 !rounded-xl"
            >
            <p class="mt-1 text-[11px] text-gray-500">Must end in {{ '@' . $allowedDomain }} (editable anytime)</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-semibold text-charcoal">School ID</label>
                <input
                    type="text"
                    name="school_id"
                    x-model="schoolId"
                    placeholder="24-12345"
                    class="mt-1.5 !rounded-xl font-mono"
                >
                <p class="mt-1 text-[11px] text-gray-500">Format: 00-00000</p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-charcoal">Sex</label>
                <select name="sex" x-model="sex" class="mt-1.5 !rounded-xl">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-semibold text-charcoal">Role *</label>
                <select name="role_id" x-model="roleId" required class="mt-1.5 !rounded-xl">
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-charcoal">SSC Officer / Position</label>
                <select name="position" x-model="position" class="mt-1.5 !rounded-xl">
                    @foreach ($positions as $pos)
                        <option value="{{ $pos }}">{{ $pos }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between gap-2">
                <label class="block text-xs font-semibold text-charcoal">Initial Password</label>
                <button
                    type="button"
                    x-on:click="fillDefaultPassword()"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 hover:underline"
                >
                    <x-icon name="sparkles" class="h-3.5 w-3.5" />
                    Auto-generate Default
                </button>
            </div>
            <div class="relative mt-1.5">
                <input
                    :type="showPassword ? 'text' : 'password'"
                    name="password"
                    x-model="password"
                    :placeholder="'Leave empty for default (e.g. ' + defaultHint() + ')'"
                    class="!rounded-xl !pr-10"
                >
                <button
                    type="button"
                    x-on:click="showPassword = !showPassword"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                    aria-label="Toggle password visibility"
                >
                    <span x-show="!showPassword"><x-icon name="eye" class="h-4 w-4" /></span>
                    <span x-show="showPassword" x-cloak><x-icon name="eye-off" class="h-4 w-4" /></span>
                </button>
            </div>
            <p class="mt-1 text-[11px] text-gray-500">
                Default formula: Capitalized name + ID number (e.g.
                <span class="font-mono text-pink-600" x-text="defaultHint()"></span>)
            </p>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
            <a href="{{ route('users.index') }}" class="btn-outline !rounded-full !px-5 !py-2.5">Cancel</a>
            <button type="submit" class="btn-maroon !rounded-full !px-6 !py-2.5">
                <x-icon name="user-plus" class="h-4 w-4" />
                Create Account
            </button>
        </div>
    </form>
</div>
@endsection
