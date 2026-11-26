@extends('layouts.app')

@section('page_title', 'Categories & Storage Locations')
@section('page_subtitle', 'Configure official property classifications and campus storage rooms')

@section('content')
<div class="space-y-6" x-data="{ tab: '{{ $activeTab === 'locations' ? 'locations' : 'categories' }}', editCategory: null, editLocation: null }">
    <!-- Tabs -->
    <div class="flex gap-2 border-b border-gray-200 pb-3">
        <button
            type="button"
            x-on:click="tab = 'categories'"
            :class="tab === 'categories' ? 'bg-maroon-900 text-white shadow-xs' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50'"
            class="rounded-xl px-4 py-2 text-xs font-semibold transition"
        >
            Inventory Categories ({{ $categories->count() }})
        </button>
        <button
            type="button"
            x-on:click="tab = 'locations'"
            :class="tab === 'locations' ? 'bg-maroon-900 text-white shadow-xs' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50'"
            class="rounded-xl px-4 py-2 text-xs font-semibold transition"
        >
            Storage Locations ({{ $locations->count() }})
        </button>
    </div>

    <!-- Categories Tab -->
    <div x-show="tab === 'categories'" class="grid gap-6 lg:grid-cols-12">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs lg:col-span-4">
            <h2 class="text-sm font-bold text-charcoal">Create Custom Category</h2>
            <p class="mt-0.5 text-xs text-gray-500">Add a new organizational property classification.</p>
            <form method="POST" action="{{ route('categories.store') }}" class="mt-4 space-y-3.5">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Category Name *</label>
                    <input type="text" name="name" required placeholder="e.g., Laboratory Equipment" class="mt-1">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Short Code *</label>
                    <input type="text" name="code" required maxlength="20" placeholder="e.g., LAB" class="mt-1 font-mono uppercase">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Description</label>
                    <textarea name="description" rows="2" class="mt-1"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span>Active for item registration</span>
                </label>
                <button type="submit" class="w-full rounded-xl bg-maroon-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-maroon-800">
                    Add Category
                </button>
            </form>
        </div>

        <div class="table-shell lg:col-span-8">
            <div class="table-scroll overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Category Name</th>
                            <th class="text-center">Items</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $cat)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 font-mono font-bold text-maroon-900">{{ $cat->code }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-charcoal">{{ $cat->name }}</p>
                                    <p class="text-[11px] text-gray-500">{{ $cat->description ?: '—' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center font-semibold">{{ $cat->items_count }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <x-status-badge :status="$cat->is_active ? 'active' : 'inactive'" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            x-on:click="editCategory = {{ Js::from($cat) }}; $dispatch('open-modal', 'edit-category')"
                                            class="rounded-lg border border-gray-200 px-2.5 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-50"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('categories.destroy', $cat) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-red-200 px-2.5 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-50">
                                                {{ $cat->items_count > 0 ? 'Deactivate' : 'Archive' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Locations Tab -->
    <div x-show="tab === 'locations'" x-cloak class="grid gap-6 lg:grid-cols-12">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs lg:col-span-4">
            <h2 class="text-sm font-bold text-charcoal">Add Storage Location</h2>
            <p class="mt-0.5 text-xs text-gray-500">Define rooms, cabinets, or storage hubs across PCB campus.</p>
            <form method="POST" action="{{ route('locations.store') }}" class="mt-4 space-y-3.5">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Location Name *</label>
                    <input type="text" name="name" required placeholder="e.g., SSC Annex Storage" class="mt-1">
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Building</label>
                        <input type="text" name="building" class="mt-1">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Room No.</label>
                        <input type="text" name="room_number" class="mt-1">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Assigned Custodian</label>
                    <select name="custodian_id" class="mt-1">
                        <option value="">None</option>
                        @foreach ($custodians as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Description</label>
                    <textarea name="description" rows="2" class="mt-1"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span>Active storage location</span>
                </label>
                <button type="submit" class="w-full rounded-xl bg-maroon-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-maroon-800">
                    Add Location
                </button>
            </form>
        </div>

        <div class="table-shell lg:col-span-8">
            <div class="table-scroll overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Location</th>
                            <th>Building / Room</th>
                            <th>Custodian</th>
                            <th class="text-center">Items</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($locations as $loc)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-charcoal">{{ $loc->name }}</p>
                                    <p class="text-[11px] text-gray-500">{{ $loc->description ?: '—' }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $loc->building ?: '—' }} {{ $loc->room_number ? "({$loc->room_number})" : '' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $loc->custodian?->name ?? 'Unassigned' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center font-semibold">{{ $loc->items_count }}</td>
                                <td class="whitespace-nowrap px-4 py-3"><x-status-badge :status="$loc->is_active ? 'active' : 'inactive'" /></td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            x-on:click="editLocation = {{ Js::from($loc) }}; $dispatch('open-modal', 'edit-location')"
                                            class="rounded-lg border border-gray-200 px-2.5 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-50"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('locations.destroy', $loc) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-red-200 px-2.5 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-50">
                                                {{ $loc->items_count > 0 ? 'Deactivate' : 'Archive' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <x-modal name="edit-category" title="Edit Inventory Category" maxWidth="md">
        <template x-if="editCategory">
            <form method="POST" :action="`{{ url('/categories') }}/${editCategory.id}`" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Category Name *</label>
                    <input type="text" name="name" x-model="editCategory.name" required class="mt-1">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Code *</label>
                    <input type="text" name="code" x-model="editCategory.code" required class="mt-1 font-mono uppercase">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Description</label>
                    <textarea name="description" rows="2" x-model="editCategory.description" class="mt-1"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                    <input type="checkbox" name="is_active" value="1" :checked="editCategory.is_active">
                    <span>Active</span>
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'edit-category')" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600">Cancel</button>
                    <button type="submit" class="rounded-lg bg-maroon-900 px-4 py-2 text-xs font-semibold text-white hover:bg-maroon-800">Update Category</button>
                </div>
            </form>
        </template>
    </x-modal>

    <!-- Edit Location Modal -->
    <x-modal name="edit-location" title="Edit Storage Location" maxWidth="md">
        <template x-if="editLocation">
            <form method="POST" :action="`{{ url('/locations') }}/${editLocation.id}`" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Location Name *</label>
                    <input type="text" name="name" x-model="editLocation.name" required class="mt-1">
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Building</label>
                        <input type="text" name="building" x-model="editLocation.building" class="mt-1">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Room No.</label>
                        <input type="text" name="room_number" x-model="editLocation.room_number" class="mt-1">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Custodian</label>
                    <select name="custodian_id" x-model="editLocation.custodian_id" class="mt-1">
                        <option value="">None</option>
                        @foreach ($custodians as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Description</label>
                    <textarea name="description" rows="2" x-model="editLocation.description" class="mt-1"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                    <input type="checkbox" name="is_active" value="1" :checked="editLocation.is_active">
                    <span>Active</span>
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'edit-location')" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600">Cancel</button>
                    <button type="submit" class="rounded-lg bg-maroon-900 px-4 py-2 text-xs font-semibold text-white hover:bg-maroon-800">Update Location</button>
                </div>
            </form>
        </template>
    </x-modal>
</div>
@endsection
