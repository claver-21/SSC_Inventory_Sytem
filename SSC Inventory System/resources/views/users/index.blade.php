@extends('layouts.app')

@section('page_title', 'Account Management')
@section('page_subtitle', 'Manage PCB-SSC administrator and student officer accounts')

@section('content')
<div
    class="space-y-5"
    x-data="{
        showAddModal: {{ $errors->any() && old('_from_modal') ? 'true' : 'false' }},
        bulkMode: {{ old('_from_bulk') ? 'true' : 'false' }},
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
    <!-- Top Account Count & Add Account Button -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs font-medium text-gray-500">
            Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of {{ $users->total() }} accounts
        </p>

        <button
            type="button"
            x-on:click="showAddModal = true; bulkMode = false"
            class="btn-maroon !rounded-full !px-5 !py-2.5"
        >
            <x-icon name="user-plus" class="h-4 w-4" />
            Add New Account
        </button>
    </div>

    <!-- Accounts Table -->
    <div class="space-y-4">
        <form method="GET" action="{{ route('users.index') }}" class="card-surface grid gap-3 p-4 sm:grid-cols-4">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Name, school ID, SSC email, position..." class="sm:col-span-2">
            <select name="role_id">
                <option value="">All Roles</option>
                @foreach ($roles as $r)
                    <option value="{{ $r->id }}" @selected(request('role_id') == $r->id)>{{ $r->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-maroon">Filter Accounts</button>
        </form>

        <div class="table-shell">
            <div class="table-scroll overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>NAME</th>
                            <th>SCHOOL ID</th>
                            <th>SSC EMAIL</th>
                            <th>SEX</th>
                            <th>ROLE</th>
                            <th>STATUS</th>
                            <th>EDIT</th>
                            <th class="text-right">REMOVE</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $u)
                            @php
                                $online = $u->isOnline();
                                $lastActivity = $u->last_seen_at ?? $u->last_login_at;
                            @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-rose-100 bg-rose-50 text-maroon-700">
                                            <x-icon name="user" class="h-4 w-4" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-charcoal">{{ $u->name }}</p>
                                            @if ($u->position)
                                                <p class="text-[11px] text-gray-500">{{ $u->position }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap font-mono text-xs font-medium text-gray-700">
                                    {{ $u->school_id ?: '—' }}
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5 text-xs text-gray-600">
                                        <x-icon name="mail" class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                        <span>{{ $u->email }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap text-xs text-gray-600">
                                    {{ $u->sex ?: '—' }}
                                </td>
                                <td class="whitespace-nowrap">
                                    @if ($u->isAdmin())
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-3 py-0.5 text-[11px] font-semibold text-maroon-800 ring-1 ring-rose-200">
                                            {{ $u->role?->name }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-sky-50 px-3 py-0.5 text-[11px] font-semibold text-sky-700 ring-1 ring-sky-200">
                                            {{ $u->role?->name }}
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if (! $u->is_active)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-semibold text-gray-600 ring-1 ring-gray-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                                Inactive
                                            </span>
                                        @elseif ($online)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                Online
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-rose-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                                Offline
                                            </span>
                                            <span class="text-[11px] text-gray-500">
                                                &middot; {{ $lastActivity ? $lastActivity->format('M j, Y, g:i A') : 'Never' }}
                                            </span>
                                        @endif

                                        @if ($u->isLocked() || $u->failed_login_attempts > 0)
                                            <form method="POST" action="{{ route('users.unlock', $u) }}">
                                                @csrf
                                                <button type="submit" class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-semibold text-amber-800 hover:bg-amber-100">
                                                    Unlock
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    <a
                                        href="{{ route('users.edit', $u) }}"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-maroon-900 transition hover:border-maroon-900 hover:bg-maroon-900 hover:text-white"
                                    >
                                        <x-icon name="edit" class="h-3.5 w-3.5" />
                                        Edit
                                    </a>
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    @if ($u->id !== auth()->id())
                                        <form
                                            method="POST"
                                            action="{{ route('users.destroy', $u) }}"
                                            onsubmit="return confirm('Remove account for {{ addslashes($u->name) }}?');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50/40 px-3.5 py-1.5 text-xs font-semibold text-rose-700 transition hover:border-rose-600 hover:bg-rose-600 hover:text-white"
                                            >
                                                <x-icon name="trash" class="h-3.5 w-3.5" />
                                                Remove
                                            </button>
                                        </form>
                                    @else
                                        <span
                                            title="You cannot remove your own active administrator account."
                                            class="inline-flex cursor-not-allowed items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3.5 py-1.5 text-xs font-semibold text-gray-400"
                                        >
                                            <x-icon name="ban" class="h-3.5 w-3.5" />
                                            Remove
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-10 text-center text-xs text-gray-500">
                                    No SSC user accounts matched your filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 bg-gray-50/40 px-5 py-3">{{ $users->links() }}</div>
        </div>
    </div>

    <!-- Add New Account / Bulk CSV Modal -->
    <div
        x-show="showAddModal"
        x-transition.opacity
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/55 p-4 backdrop-blur-xs"
        x-on:keydown.escape.window="showAddModal = false"
    >
        <div
            x-on:click.outside="showAddModal = false"
            class="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-black/5"
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-rose-50 text-maroon-900">
                        <x-icon name="user-plus" class="h-5 w-5" />
                    </span>
                    <h2 class="text-base font-bold text-charcoal">
                        <span x-text="bulkMode ? 'Bulk Import SSC Accounts' : 'Add New Account'"></span>
                    </h2>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        x-on:click="bulkMode = !bulkMode"
                        class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50/70 px-3 py-1.5 text-xs font-semibold text-maroon-900 transition hover:bg-rose-100"
                    >
                        <x-icon name="upload" class="h-3.5 w-3.5" />
                        <span x-text="bulkMode ? 'Single Form' : 'Bulk Import (CSV)'"></span>
                    </button>
                    <button
                        type="button"
                        x-on:click="showAddModal = false"
                        class="rounded-full p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                        aria-label="Close modal"
                    >
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- Single Account Form -->
            <form
                x-show="!bulkMode"
                method="POST"
                action="{{ route('users.store') }}"
                class="space-y-4 px-6 py-5"
            >
                @csrf
                <input type="hidden" name="_from_modal" value="1">

                @if ($errors->any() && old('_from_modal') && ! old('_from_bulk'))
                    <div class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-xs text-red-700">
                        <ul class="list-disc space-y-0.5 pl-4">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

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
                    <button
                        type="button"
                        x-on:click="showAddModal = false"
                        class="btn-outline !rounded-full !px-5 !py-2.5"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="btn-maroon !rounded-full !px-6 !py-2.5"
                    >
                        <x-icon name="user-plus" class="h-4 w-4" />
                        Create Account
                    </button>
                </div>
            </form>

            <!-- Bulk Import CSV Form -->
            <form
                x-show="bulkMode"
                x-cloak
                method="POST"
                action="{{ route('users.bulk-import') }}"
                enctype="multipart/form-data"
                class="space-y-4 px-6 py-5"
            >
                @csrf
                <input type="hidden" name="_from_modal" value="1">
                <input type="hidden" name="_from_bulk" value="1">

                @if ($errors->any() && old('_from_bulk'))
                    <div class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-xs text-red-700">
                        <ul class="list-disc space-y-0.5 pl-4">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="rounded-2xl border border-rose-100 bg-rose-50/50 p-4 text-xs text-gray-700 space-y-2">
                    <p class="font-semibold text-maroon-950">CSV Format Instructions</p>
                    <p>Upload a UTF-8 CSV file with columns: <code class="font-mono text-[11px]">first_name, last_name, email, school_id, sex, role, position, password</code>. Emails must end with <strong class="text-maroon-900">{{ '@' . $allowedDomain }}</strong>.</p>
                    <a href="{{ route('users.bulk-template') }}" class="inline-flex items-center gap-1.5 font-semibold text-maroon-900 hover:underline">
                        <x-icon name="download" class="h-3.5 w-3.5" />
                        Download CSV Template
                    </a>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-charcoal">Select CSV File *</label>
                    <input type="file" name="csv_file" accept=".csv,.txt" required class="mt-1.5 !rounded-xl">
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                    <button
                        type="button"
                        x-on:click="bulkMode = false"
                        class="btn-outline !rounded-full !px-5 !py-2.5"
                    >
                        Back to Single Form
                    </button>
                    <button
                        type="submit"
                        class="btn-maroon !rounded-full !px-6 !py-2.5"
                    >
                        <x-icon name="upload" class="h-4 w-4" />
                        Import CSV Accounts
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
