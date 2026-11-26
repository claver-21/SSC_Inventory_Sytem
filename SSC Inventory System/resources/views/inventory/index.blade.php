@extends('layouts.app')

@section('page_title', 'Inventory Records')
@section('page_subtitle', 'Centralized registry of all PCB-SSC organizational properties, equipment, and supplies')

@section('content')
<div class="space-y-5">
    <!-- Toolbar & Actions -->
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div class="inline-flex rounded-xl border border-gray-200 bg-white p-1 shadow-xs">
            <a
                href="{{ route('inventory.index') }}"
                class="rounded-lg px-3.5 py-1.5 text-xs font-semibold transition {{ ! $showArchived ? 'bg-maroon-900 text-white shadow-xs' : 'text-gray-600 hover:text-charcoal' }}"
            >
                Active Registry
            </a>
            @permission('inventory.archive')
                <a
                    href="{{ route('inventory.index', ['archived' => 1]) }}"
                    class="rounded-lg px-3.5 py-1.5 text-xs font-semibold transition {{ $showArchived ? 'bg-maroon-900 text-white shadow-xs' : 'text-gray-600 hover:text-charcoal' }}"
                >
                    Archived Items
                </a>
            @endpermission
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @permission('categories.manage')
                <a href="{{ route('categories.index') }}" class="btn-outline">
                    <x-icon name="categories" class="w-4 h-4" />
                    Categories &amp; Locations
                </a>
            @endpermission
            @permission('stock.view')
                <a href="{{ route('stock-movements.index') }}" class="btn-outline">
                    <x-icon name="stock" class="w-4 h-4" />
                    Stock Logs
                </a>
            @endpermission
            @permission('inventory.manage')
                <a href="{{ route('inventory.bulk.create') }}" class="btn-soft">
                    <x-icon name="clipboard" class="w-4 h-4" />
                    Bulk Registration
                </a>
                <a href="{{ route('inventory.create') }}" class="btn-maroon">
                    <x-icon name="plus" class="w-4 h-4" />
                    Add New Item
                </a>
            @endpermission
        </div>
    </div>

    <!-- Search & Filter Card -->
    <form method="GET" action="{{ route('inventory.index') }}" class="card-surface p-4">
        @if ($showArchived)
            <input type="hidden" name="archived" value="1">
        @endif
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-bold tracking-wide text-gray-500 uppercase">Search</label>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Property code, item name, brand..." class="mt-1">
            </div>

            <div>
                <label class="block text-[11px] font-bold tracking-wide text-gray-500 uppercase">Category</label>
                <select name="category_id" class="mt-1">
                    <option value="">All Categories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold tracking-wide text-gray-500 uppercase">Location</label>
                <select name="location_id" class="mt-1">
                    <option value="">All Locations</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(request('location_id') == $loc->id)>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold tracking-wide text-gray-500 uppercase">Availability</label>
                <select name="status" class="mt-1">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold tracking-wide text-gray-500 uppercase">Condition</label>
                <select name="condition_id" class="mt-1">
                    <option value="">All Conditions</option>
                    @foreach ($conditions as $cond)
                        <option value="{{ $cond->id }}" @selected(request('condition_id') == $cond->id)>{{ $cond->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-3.5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3">
            <div class="flex flex-wrap items-center gap-4">
                <div class="w-48">
                    <select name="responsible_user_id" class="!py-1.5 !text-xs">
                        <option value="">All Responsible Officers</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected(request('responsible_user_id') == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                    <input type="checkbox" name="low_stock" value="1" @checked(request()->boolean('low_stock'))>
                    <span>Low stock items only</span>
                </label>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('inventory.index', $showArchived ? ['archived' => 1] : []) }}" class="btn-outline !py-1.5">
                    Reset
                </a>
                <button type="submit" class="btn-maroon !py-1.5">
                    Apply Filters
                </button>
            </div>
        </div>
    </form>

    <!-- Inventory Table -->
    @if ($items->isEmpty())
        <x-empty-state
            title="No inventory items found"
            message="{{ $showArchived ? 'There are no archived inventory items.' : 'Start by registering your first item or adjusting your search filters.' }}"
            icon="inventory"
            :actionLabel="auth()->user()->hasPermission('inventory.manage') && !$showArchived ? 'Add Inventory Item' : null"
            :actionHref="auth()->user()->hasPermission('inventory.manage') && !$showArchived ? route('inventory.create') : null"
        />
    @else
        <div class="table-shell">
            <div class="table-scroll overflow-x-auto">
                <table class="data-table">
                    <thead>
                        @php
                            $sortUrl = fn (string $col) => request()->fullUrlWithQuery([
                                'sort' => $col,
                                'dir' => ($sortBy === $col && $sortDir === 'asc') ? 'desc' : 'asc',
                            ]);
                        @endphp
                        <tr>
                            <th>
                                <a href="{{ $sortUrl('property_code') }}" class="inline-flex items-center gap-1 hover:text-maroon-900">
                                    Property Code
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortUrl('name') }}" class="inline-flex items-center gap-1 hover:text-maroon-900">
                                    Item &amp; Brand
                                </a>
                            </th>
                            <th>Category</th>
                            <th class="text-center">
                                <a href="{{ $sortUrl('available_quantity') }}" class="inline-flex items-center gap-1 hover:text-maroon-900">
                                    Stock Level
                                </a>
                            </th>
                            <th>Availability</th>
                            <th>Condition</th>
                            <th>Location &amp; Custodian</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            @php
                                $pct = $item->quantity > 0 ? min(100, round(($item->available_quantity / $item->quantity) * 100)) : 0;
                                $barColor = $item->available_quantity === 0 ? 'bg-red-500' : ($item->isLowStock() ? 'bg-amber-500' : 'bg-emerald-500');
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap">
                                    <span class="inline-flex rounded-lg bg-maroon-50 px-2.5 py-1 font-mono text-[11px] font-bold text-maroon-900 ring-1 ring-maroon-200/70">
                                        {{ $item->property_code }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-3.5">
                                        @if ($item->imageUrl())
                                            <img src="{{ $item->imageUrl() }}" alt="{{ $item->name }}" class="h-10 w-10 shrink-0 rounded-xl border border-gray-200 object-cover shadow-2xs">
                                        @else
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-maroon-50 to-maroon-100 text-maroon-800 ring-1 ring-maroon-200/60">
                                                <x-icon name="inventory" class="w-5 h-5" />
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="font-bold text-charcoal">
                                                {{ $item->name }}
                                            </p>
                                            <p class="truncate text-[11px] text-gray-500">
                                                {{ $item->brand_model ?: 'Standard equipment / supply' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    <span class="rounded-lg bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-gray-700">
                                        {{ $item->category?->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="mx-auto w-28 text-center">
                                        <div class="text-xs tabular-nums">
                                            <span class="font-extrabold {{ $item->isLowStock() ? 'text-amber-700' : 'text-charcoal' }}">{{ $item->available_quantity }}</span>
                                            <span class="text-gray-400">/ {{ $item->quantity }} {{ $item->unit_of_measurement }}</span>
                                        </div>
                                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                                            <div class="h-full rounded-full {{ $barColor }}" style="width: {{ $pct }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    <x-status-badge :status="$item->status" />
                                </td>
                                <td class="whitespace-nowrap">
                                    <x-condition-badge :condition="$item->condition" />
                                </td>
                                <td>
                                    <p class="font-semibold text-charcoal">{{ $item->location?->name ?? 'Unassigned' }}</p>
                                    <p class="text-[11px] text-gray-500">{{ $item->responsibleUser?->name ?? 'No officer assigned' }}</p>
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        @unless (auth()->user()->isAdmin())
                                            @if (!$showArchived && $item->status === 'available' && $item->available_quantity > 0)
                                                @permission('borrowings.request')
                                                    <a href="{{ route('borrowings.create', ['item_id' => $item->id]) }}" class="btn-maroon !rounded-lg !px-2.5 !py-1 !text-[11px]">
                                                        Borrow
                                                    </a>
                                                @endpermission
                                            @endif
                                        @endunless
                                        @if (!$showArchived)
                                            @permission('inventory.manage')
                                                <a href="{{ route('inventory.edit', $item) }}" class="btn-soft !rounded-lg !px-2.5 !py-1 !text-[11px]">
                                                    Edit
                                                </a>
                                            @endpermission
                                            @permission('inventory.archive')
                                                <form method="POST" action="{{ route('inventory.archive', $item) }}" onsubmit="return confirm('Remove {{ addslashes($item->name) }} from active inventory?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg border border-red-200 bg-red-50/70 px-2.5 py-1 text-[11px] font-semibold text-red-700 transition hover:border-red-600 hover:bg-red-600 hover:text-white">
                                                        Remove
                                                    </button>
                                                </form>
                                            @endpermission
                                        @else
                                            @permission('inventory.archive')
                                                <form method="POST" action="{{ route('inventory.restore', $item->id) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-emerald-700">
                                                        Restore
                                                    </button>
                                                </form>
                                            @endpermission
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 bg-gray-50/40 px-5 py-3">
                {{ $items->links() }}
            </div>
        </div>
    @endif
</div>
@endsection
