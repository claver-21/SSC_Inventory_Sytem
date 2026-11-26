@extends('layouts.app')

@section('page_title', 'Dashboard')
@section('page_subtitle', 'Simplified PCB Supreme Student Council equipment, supplies, and borrow tracking overview')
@section('hide_page_header', true)

@section('content')
<div class="space-y-6">
    @if ($kpis['overdue_returns'] > 0)
        <div class="flex flex-col justify-between gap-3 rounded-2xl border border-red-200 bg-gradient-to-r from-red-50 to-rose-50/80 px-5 py-4 text-red-900 shadow-xs sm:flex-row sm:items-center">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700 ring-1 ring-red-200">
                    <x-icon name="alert" class="w-5 h-5" />
                </div>
                <div>
                    <p class="text-sm font-bold">
                        {{ $kpis['overdue_returns'] }} Overdue Equipment Return{{ $kpis['overdue_returns'] > 1 ? 's' : '' }}
                    </p>
                    <p class="text-xs text-red-700">Verify item count &amp; condition immediately upon return.</p>
                </div>
            </div>
            <a href="{{ route('borrowings.index', ['tab' => 'overdue']) }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-red-700 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-red-800">
                Review Overdue &rarr;
            </a>
        </div>
    @endif

    <!-- Institutional Wine-Maroon Welcome Hero Banner -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#3B0F1A] via-[#5B1424] to-[#7D1D31] p-6 text-white shadow-[0_10px_28px_rgba(59,15,26,0.22)]">
        <div class="pointer-events-none absolute -top-12 -right-12 h-44 w-44 rounded-full bg-white/[0.06]"></div>
        <div class="pointer-events-none absolute -bottom-16 right-24 h-36 w-36 rounded-full bg-white/[0.04]"></div>

        <div class="relative flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
            <div class="flex items-center gap-4">
                <img src="{{ asset('images/pcb-ssc-logo.png') }}" alt="PCB SSC" class="h-14 w-14 shrink-0 rounded-full bg-white p-1 object-contain shadow-md ring-2 ring-white/25">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-white/15 px-2.5 py-0.5 text-[10px] font-bold tracking-wider uppercase ring-1 ring-white/20">
                            {{ auth()->user()->role?->name }}
                        </span>
                        <span class="text-[11px] text-white/70">{{ now()->format('l, M d, Y') }}</span>
                    </div>
                    <h2 class="mt-1 text-lg font-extrabold tracking-tight sm:text-xl">
                        Welcome back, {{ auth()->user()->name }}
                    </h2>
                    <p class="text-xs text-white/75">
                        PCB Supreme Student Council — Centralized Equipment &amp; Supply Tracker
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                @permission('inventory.manage')
                    <a href="{{ route('inventory.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-maroon-950 shadow-sm transition hover:bg-maroon-50">
                        <x-icon name="plus" class="w-4 h-4" />
                        Add Item
                    </a>
                @endpermission
                @unless (auth()->user()->isAdmin())
                    @permission('borrowings.request')
                        <a href="{{ route('borrowings.create') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-xs font-semibold text-white backdrop-blur-xs transition hover:bg-white/20">
                            <x-icon name="borrowing" class="w-4 h-4" />
                            Borrow Equipment
                        </a>
                    @endpermission
                    @permission('incidents.report')
                        <a href="{{ route('incidents.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-xs font-semibold text-white backdrop-blur-xs transition hover:bg-white/20">
                            <x-icon name="alert" class="w-4 h-4" />
                            Report Incident
                        </a>
                    @endpermission
                @endunless
            </div>
        </div>
    </div>

    <!-- 6 Focused Inventory & Borrow KPI Cards -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-stat-card
            title="Total Inventory Items"
            :value="number_format($kpis['total_items'])"
            :subtitle="number_format($kpis['total_units']) . ' total unit(s) tracked'"
            icon="inventory"
            tone="maroon"
            :href="route('inventory.index')"
        />
        <x-stat-card
            title="Available Stock"
            :value="number_format($kpis['available_items'])"
            :subtitle="number_format($kpis['available_units']) . ' unit(s) ready for events'"
            icon="check"
            tone="emerald"
            :href="route('inventory.index', ['status' => 'available'])"
        />
        <x-stat-card
            title="Currently Borrowed"
            :value="number_format($kpis['borrowed_units'])"
            :subtitle="$kpis['active_loans'] . ' active loan(s)'"
            icon="borrowing"
            tone="blue"
            :href="route('borrowings.index', ['tab' => 'active'])"
        />
        <x-stat-card
            title="Low Stock Alerts"
            :value="number_format($kpis['low_stock_items'])"
            subtitle="Items at or below minimum count"
            icon="alert"
            tone="amber"
            :href="route('inventory.index', ['low_stock' => 1])"
        />
        <x-stat-card
            title="Damaged / Missing"
            :value="number_format($kpis['damaged_missing_items'])"
            :subtitle="$kpis['pending_inspections'] . ' return(s) awaiting check'"
            icon="alert"
            tone="orange"
            :href="route('inventory.index', ['status' => 'damaged'])"
        />
        <x-stat-card
            title="Overdue Returns"
            :value="number_format($kpis['overdue_returns'])"
            :subtitle="$isScopedMember ? 'Your overdue borrowed items' : 'Past expected return date'"
            icon="logs"
            tone="red"
            :href="route('borrowings.index', ['tab' => 'overdue'])"
        />
    </div>
</div>
@endsection
