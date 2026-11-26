@extends('layouts.app')

@section('page_title', 'Edit User Account')
@section('page_subtitle', $userRecord->name . ' (' . $userRecord->email . ')')

@section('content')
<form method="POST" action="{{ route('users.update', $userRecord) }}" class="card-surface mx-auto max-w-2xl space-y-5 p-6">
    @csrf
    @method('PUT')

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="block text-xs font-semibold text-charcoal">Full Name *</label>
            <input type="text" name="name" value="{{ old('name', $userRecord->name) }}" required class="mt-1 !rounded-xl">
        </div>
        <div>
            <label class="block text-xs font-semibold text-charcoal">SSC Institutional Email *</label>
            <input type="email" name="email" value="{{ old('email', $userRecord->email) }}" required class="mt-1 !rounded-xl">
        </div>
        <div>
            <label class="block text-xs font-semibold text-charcoal">Role *</label>
            <select name="role_id" required class="mt-1 !rounded-xl">
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected(old('role_id', $userRecord->role_id) == $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-charcoal">SSC Officer / Position</label>
            <select name="position" class="mt-1 !rounded-xl">
                @php $currentPosition = old('position', $userRecord->position); @endphp
                @foreach ($positions as $pos)
                    <option value="{{ $pos }}" @selected($currentPosition === $pos)>{{ $pos }}</option>
                @endforeach
                @if ($currentPosition && ! in_array($currentPosition, $positions, true))
                    <option value="{{ $currentPosition }}" selected>{{ $currentPosition }}</option>
                @endif
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-charcoal">School ID</label>
            <input type="text" name="school_id" value="{{ old('school_id', $userRecord->school_id) }}" placeholder="24-12345" class="mt-1 !rounded-xl font-mono">
        </div>
        <div>
            <label class="block text-xs font-semibold text-charcoal">Sex</label>
            <select name="sex" class="mt-1 !rounded-xl">
                <option value="">Select sex...</option>
                <option value="Male" @selected(old('sex', $userRecord->sex) === 'Male')>Male</option>
                <option value="Female" @selected(old('sex', $userRecord->sex) === 'Female')>Female</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-charcoal">Department / Committee</label>
            <input type="text" name="department" value="{{ old('department', $userRecord->department) }}" class="mt-1 !rounded-xl">
        </div>
        <div class="flex items-end pb-2">
            <label class="inline-flex items-center gap-2 text-xs font-semibold text-charcoal">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $userRecord->is_active))>
                <span>Account Active</span>
            </label>
        </div>
    </div>

    <div class="grid gap-4 border-t border-gray-100 pt-4 sm:grid-cols-2">
        <div x-data="{ show: false }">
            <label class="block text-xs font-semibold text-charcoal">New Password (min 12 chars; blank to keep)</label>
            <div class="relative mt-1">
                <input :type="show ? 'text' : 'password'" name="password" autocomplete="new-password" placeholder="Leave blank to keep current password" class="!rounded-xl !pr-10">
                <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition hover:text-gray-600" aria-label="Toggle password visibility">
                    <span x-show="!show"><x-icon name="eye" class="h-4 w-4" /></span>
                    <span x-show="show" x-cloak><x-icon name="eye-off" class="h-4 w-4" /></span>
                </button>
            </div>
        </div>
        <div x-data="{ show: false }">
            <label class="block text-xs font-semibold text-charcoal">Confirm New Password</label>
            <div class="relative mt-1">
                <input :type="show ? 'text' : 'password'" name="password_confirmation" autocomplete="new-password" placeholder="Re-enter new password" class="!rounded-xl !pr-10">
                <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition hover:text-gray-600" aria-label="Toggle password visibility">
                    <span x-show="!show"><x-icon name="eye" class="h-4 w-4" /></span>
                    <span x-show="show" x-cloak><x-icon name="eye-off" class="h-4 w-4" /></span>
                </button>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
        <a href="{{ route('users.index') }}" class="btn-outline !rounded-full !px-5 !py-2.5">Cancel</a>
        <button type="submit" class="btn-maroon !rounded-full !px-6 !py-2.5">Save Changes</button>
    </div>
</form>
@endsection
