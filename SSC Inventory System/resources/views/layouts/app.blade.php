<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('page_title', 'Dashboard') — PCB-SSC SecureLocal</title>
    <link rel="icon" type="image/png" href="{{ asset('images/pcb-ssc-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full bg-surface-bg text-charcoal antialiased" x-data="{ sidebarOpen: false }">
    <div class="flex min-h-screen">
        <!-- Mobile Sidebar Backdrop -->
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            x-cloak
            class="fixed inset-0 z-40 bg-gray-900/60 backdrop-blur-xs lg:hidden"
            x-on:click="sidebarOpen = false"
        ></div>

        @php
            $currentUser = auth()->user();
            $unreadNotifCount = $currentUser ? $currentUser->appNotifications()->whereNull('read_at')->count() : 0;
        @endphp

        <!-- Fixed Institutional Wine-Maroon Sidebar -->
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="no-print fixed inset-y-0 left-0 z-50 flex h-screen w-64 flex-col border-r border-white/10 bg-gradient-to-b from-[#3B0F1A] via-[#471320] to-[#320B14] text-white shadow-2xl transition-transform duration-200 lg:translate-x-0"
        >
            <!-- Brand Header -->
            <div class="flex shrink-0 items-center gap-3 border-b border-white/10 px-5 py-4.5">
                <img
                    src="{{ asset('images/pcb-ssc-logo.png') }}"
                    alt="PCB Supreme Student Council Seal"
                    class="h-11 w-11 shrink-0 rounded-full bg-white p-0.5 object-contain shadow-sm"
                >
                <div class="min-w-0 flex-1">
                    <p class="truncate text-base font-extrabold tracking-tight text-white">SecureLocal</p>
                    <p class="truncate text-[11px] font-medium text-white/60">PCB — SSC Inventory</p>
                </div>
                <a
                    href="{{ route('notifications.index') }}"
                    title="Notifications"
                    aria-label="Notifications"
                    class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-white/15 bg-white/[0.08] text-white/85 transition hover:bg-white/15 hover:text-white"
                >
                    <x-icon name="bell" class="h-4 w-4" />
                    @if ($unreadNotifCount > 0)
                        <span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white ring-2 ring-[#3B0F1A]">
                            {{ $unreadNotifCount > 99 ? '99+' : $unreadNotifCount }}
                        </span>
                    @endif
                </a>
                <button type="button" class="rounded-lg p-1 text-white/70 hover:bg-white/10 lg:hidden" x-on:click="sidebarOpen = false">
                    <x-icon name="x" class="w-5 h-5" />
                </button>
            </div>

            <!-- Relocated Global Search Bar -->
            <div
                class="relative z-30 shrink-0 px-3.5 pt-3.5 pb-1"
                x-data="{
                    query: '',
                    open: false,
                    loading: false,
                    results: [],
                    async search() {
                        if (this.query.trim().length < 2) {
                            this.results = [];
                            this.open = false;
                            return;
                        }
                        this.loading = true;
                        try {
                            const res = await fetch(`{{ route('search.quick') }}?q=${encodeURIComponent(this.query.trim())}`, {
                                headers: { 'Accept': 'application/json' }
                            });
                            if (res.ok) {
                                const data = await res.json();
                                this.results = data.results || [];
                                this.open = true;
                            }
                        } finally {
                            this.loading = false;
                        }
                    }
                }"
                x-on:click.outside="open = false"
            >
                <form action="{{ route('search.index') }}" method="GET" class="relative">
                    <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3.5 h-3.5 w-3.5 text-white/55" />
                    <input
                        type="search"
                        name="q"
                        x-model="query"
                        x-on:input.debounce.300ms="search()"
                        x-on:focus="if (results.length) open = true"
                        placeholder="Search SSC items or loans..."
                        class="!w-full !rounded-full !border !border-white/15 !bg-white/[0.08] !py-2 !pr-3 !pl-9 !text-xs !text-white placeholder:!text-white/50 focus:!border-white/35 focus:!bg-white/[0.14] focus:!ring-0 focus:!outline-none"
                    >
                </form>

                <div x-show="open" x-transition x-cloak class="absolute left-3.5 right-3.5 mt-1.5 overflow-hidden rounded-xl border border-gray-200 bg-white text-charcoal shadow-2xl lg:right-auto lg:w-72">
                    <div class="max-h-64 divide-y divide-gray-100 overflow-y-auto">
                        <template x-for="row in results" :key="row.url + row.title">
                            <a :href="row.url" class="flex items-center justify-between gap-3 px-3.5 py-2.5 hover:bg-maroon-50/60">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-charcoal" x-text="row.title"></p>
                                    <p class="truncate text-[11px] text-gray-500" x-text="row.subtitle"></p>
                                </div>
                                <span class="shrink-0 rounded-full bg-maroon-50 px-2 py-0.5 text-[10px] font-semibold text-maroon-800" x-text="row.badge"></span>
                            </a>
                        </template>
                        <div x-show="!loading && results.length === 0" class="px-4 py-4 text-center text-xs text-gray-500">
                            No matching records found.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Menu Links (Unchanged Names) -->
            <nav class="sidebar-scroll flex-1 space-y-1.5 overflow-y-auto px-3.5 py-4 text-[13px]">
                @php
                    $navItems = [
                        ['label' => 'Dashboard', 'route' => 'dashboard', 'pattern' => 'dashboard', 'icon' => 'dashboard', 'perm' => ['dashboard.view']],
                        ['label' => 'Inventory', 'route' => 'inventory.index', 'pattern' => 'inventory.*|categories.*|locations.*|stock-movements.*', 'icon' => 'inventory', 'perm' => ['inventory.view']],
                        ['label' => 'Borrow & Return', 'route' => 'borrowings.index', 'pattern' => 'borrowings.*', 'icon' => 'borrowing', 'perm' => ['borrowings.view_own', 'borrowings.view_all']],
                        ['label' => 'Incidents', 'route' => 'incidents.index', 'pattern' => 'incidents.*', 'icon' => 'alert', 'perm' => ['incidents.report']],
                        ['label' => 'Reports', 'route' => 'reports.index', 'pattern' => 'reports.*', 'icon' => 'reports', 'perm' => ['reports.view_basic', 'reports.view_all']],
                        ['label' => 'Users & Roles', 'route' => 'users.index', 'pattern' => 'users.*', 'icon' => 'users', 'perm' => ['users.manage']],
                        ['label' => 'Settings & Backup', 'route' => 'settings.index', 'pattern' => 'settings.*', 'icon' => 'settings', 'perm' => ['settings.manage']],
                    ];
                @endphp

                @foreach ($navItems as $item)
                    @if ($currentUser && ($item['perm'] === null || $currentUser->hasAnyPermission($item['perm'])))
                        @php
                            $patterns = explode('|', $item['pattern']);
                            $active = collect($patterns)->contains(fn ($p) => request()->routeIs($p));
                        @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            class="flex items-center gap-3.5 rounded-full px-4 py-2.5 transition duration-150 {{ $active ? 'border border-white/25 bg-gradient-to-b from-[#8A3A4E]/80 via-[#6E2336]/90 to-[#561828] font-semibold text-white shadow-[0_6px_18px_rgba(0,0,0,0.35),inset_0_1px_1px_rgba(255,255,255,0.35)]' : 'font-medium text-white/80 hover:bg-white/[0.06] hover:text-white' }}"
                        >
                            <x-icon :name="$item['icon']" class="h-[18px] w-[18px] shrink-0 {{ $active ? 'text-white' : 'text-white/75' }}" />
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </nav>

            <!-- Pinned User Profile Card & Logout Button -->
            <div class="shrink-0 space-y-2.5 border-t border-white/10 bg-black/15 p-3.5">
                <a
                    href="{{ route('profile.edit') }}"
                    class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.06] p-3 transition hover:bg-white/[0.1]"
                >
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/20 bg-[#6A2838] text-white">
                        <x-icon name="user" class="h-4 w-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-bold text-white">{{ $currentUser?->name }}</p>
                        <p class="truncate text-[11px] text-white/60">{{ $currentUser?->role?->name }}</p>
                    </div>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-full border border-rose-400/35 bg-[#551725]/80 px-4 py-2.5 text-xs font-semibold text-rose-300 transition hover:bg-[#6E1F31] hover:text-rose-200"
                    >
                        <x-icon name="logout" class="h-4 w-4" />
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="flex min-h-screen min-w-0 flex-1 flex-col lg:pl-64">
            <!-- Mobile-Only Drawer Trigger Bar -->
            <div class="no-print flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3 lg:hidden">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white p-2 text-gray-600 hover:bg-gray-50"
                        x-on:click="sidebarOpen = true"
                        aria-label="Open menu"
                    >
                        <x-icon name="menu" class="w-5 h-5" />
                    </button>
                    <span class="text-sm font-bold tracking-tight text-maroon-950">PCB — SSC Inventory</span>
                </div>
                <a href="{{ route('notifications.index') }}" class="relative flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600" aria-label="Notifications">
                    <x-icon name="bell" class="w-4 h-4" />
                    @if ($unreadNotifCount > 0)
                        <span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-maroon-700 px-1 text-[10px] font-bold text-white ring-2 ring-white">
                            {{ $unreadNotifCount > 99 ? '99+' : $unreadNotifCount }}
                        </span>
                    @endif
                </a>
            </div>

            <main class="page-enter flex-1 p-5 sm:p-7 lg:px-8 lg:py-7">
                @unless (View::hasSection('hide_page_header'))
                    @hasSection('page_title')
                        <div class="no-print mb-6">
                            <h1 class="text-2xl font-extrabold tracking-tight text-charcoal">@yield('page_title')</h1>
                            @hasSection('page_subtitle')
                                <p class="mt-1 text-sm text-gray-500">@yield('page_subtitle')</p>
                            @endif
                        </div>
                    @endif
                @endunless
                @if (session('success'))
                    <div class="no-print mb-5 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-xs">
                        <x-icon name="check" class="w-5 h-5 shrink-0 text-emerald-600" />
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if (session('error'))
                    <div class="no-print mb-5 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-xs">
                        <x-icon name="alert" class="w-5 h-5 shrink-0 text-red-600" />
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="no-print mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-xs">
                        <div class="flex items-center gap-2 font-semibold">
                            <x-icon name="alert" class="w-5 h-5 shrink-0 text-red-600" />
                            <span>Please review the following issues:</span>
                        </div>
                        <ul class="mt-1.5 list-disc space-y-0.5 pl-7 text-xs">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>

            <footer class="no-print border-t border-gray-200/70 bg-white/60 px-6 py-3 text-xs text-gray-500">
                <div class="flex flex-col items-center justify-between gap-2 sm:flex-row">
                    <span>Polytechnic College of Botolan — Supreme Student Council (SecureLocal Inventory)</span>
                    <span>&copy; {{ date('Y') }} PCB-SSC · RA 10173 Compliant</span>
                </div>
            </footer>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
