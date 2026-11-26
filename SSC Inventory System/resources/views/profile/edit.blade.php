@extends('layouts.app')

@section('page_title', 'Account Settings')
@section('page_subtitle', 'Manage your profile details and password')

@section('content')
<div class="grid gap-6 lg:grid-cols-2">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
        <div class="border-b border-gray-100 pb-4">
            <h2 class="text-base font-bold text-charcoal">Profile Information</h2>
            <p class="mt-0.5 text-xs text-gray-500">Update your council designation, school ID, and contact details.</p>
        </div>

        <form method="POST" action="{{ route('profile.update') }}" class="mt-5 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-semibold text-gray-700">Full Name</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="mt-1">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Email Address</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="mt-1">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700">School ID</label>
                    <input type="text" name="school_id" value="{{ old('school_id', $user->school_id) }}" class="mt-1">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-gray-700">SSC Position</label>
                    <input type="text" name="position" value="{{ old('position', $user->position) }}" class="mt-1">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Department / Committee</label>
                    <input type="text" name="department" value="{{ old('department', $user->department) }}" class="mt-1">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700">Assigned Role</label>
                <input type="text" value="{{ $user->role?->name }}" disabled class="mt-1">
            </div>

            <div class="pt-2">
                <button type="submit" class="rounded-xl bg-maroon-900 px-5 py-2.5 text-xs font-semibold text-white shadow-xs transition hover:bg-maroon-800">
                    Save Profile
                </button>
            </div>
        </form>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
        <div class="border-b border-gray-100 pb-4">
            <h2 class="text-base font-bold text-charcoal">Update Password</h2>
            <p class="mt-0.5 text-xs text-gray-500">Use a strong password containing at least 8 characters with letters and numbers.</p>
        </div>

        <form method="POST" action="{{ route('profile.password') }}" class="mt-5 space-y-4">
            @csrf
            @method('PUT')

            <div x-data="{ show: false }">
                <label class="block text-xs font-semibold text-gray-700">Current Password</label>
                <div class="relative mt-1">
                    <input :type="show ? 'text' : 'password'" name="current_password" autocomplete="current-password" required class="!pr-10">
                    <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition hover:text-gray-600" aria-label="Toggle password visibility">
                        <span x-show="!show"><x-icon name="eye" class="h-4 w-4" /></span>
                        <span x-show="show" x-cloak><x-icon name="eye-off" class="h-4 w-4" /></span>
                    </button>
                </div>
            </div>

            <div x-data="{ show: false }">
                <label class="block text-xs font-semibold text-gray-700">New Password</label>
                <div class="relative mt-1">
                    <input :type="show ? 'text' : 'password'" name="password" autocomplete="new-password" required class="!pr-10">
                    <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition hover:text-gray-600" aria-label="Toggle password visibility">
                        <span x-show="!show"><x-icon name="eye" class="h-4 w-4" /></span>
                        <span x-show="show" x-cloak><x-icon name="eye-off" class="h-4 w-4" /></span>
                    </button>
                </div>
            </div>

            <div x-data="{ show: false }">
                <label class="block text-xs font-semibold text-gray-700">Confirm New Password</label>
                <div class="relative mt-1">
                    <input :type="show ? 'text' : 'password'" name="password_confirmation" autocomplete="new-password" required class="!pr-10">
                    <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition hover:text-gray-600" aria-label="Toggle password visibility">
                        <span x-show="!show"><x-icon name="eye" class="h-4 w-4" /></span>
                        <span x-show="show" x-cloak><x-icon name="eye-off" class="h-4 w-4" /></span>
                    </button>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="rounded-xl bg-maroon-900 px-5 py-2.5 text-xs font-semibold text-white shadow-xs transition hover:bg-maroon-800">
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
