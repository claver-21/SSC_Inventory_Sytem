@extends('layouts.app')

@section('page_title', 'Incident Response Center')
@section('page_subtitle', 'Report and track damaged/missing equipment or security events through NIST CSF response stages')

@section('content')
<div class="grid gap-6 lg:grid-cols-12" x-data="{ cat: 'equipment' }">
    <!-- Submit Incident Form -->
    <div class="card-surface p-5 lg:col-span-4 h-fit">
        <h2 class="border-b border-gray-100 pb-3 text-sm font-bold text-charcoal">Report New Incident</h2>
        <form method="POST" action="{{ route('incidents.store') }}" class="mt-4 space-y-3.5">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-gray-700">Incident Category *</label>
                <select name="category" x-model="cat" class="mt-1">
                    <option value="equipment">Equipment Damage / Loss</option>
                    <option value="security">Security / Phishing / Account Alert</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700">Incident Type *</label>
                <select name="incident_type" required class="mt-1">
                    <template x-if="cat === 'equipment'">
                        <optgroup label="Equipment">
                            <option value="damaged">Damaged Equipment</option>
                            <option value="missing">Missing Equipment</option>
                        </optgroup>
                    </template>
                    <template x-if="cat === 'security'">
                        <optgroup label="Security">
                            <option value="phishing">Phishing / Scam Link in Group Chat</option>
                            <option value="suspicious_login">Suspicious Login / Account Alert</option>
                            <option value="unauthorized_access">Unauthorized Access Attempt</option>
                        </optgroup>
                    </template>
                </select>
            </div>

            <div x-show="cat === 'equipment'">
                <label class="block text-xs font-semibold text-gray-700">Affected SSC Item *</label>
                <select name="inventory_item_id" class="mt-1">
                    <option value="">Select inventory item...</option>
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}">[{{ $item->property_code }}] {{ $item->name }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="cat === 'equipment'">
                <label class="block text-xs font-semibold text-gray-700">Affected Quantity</label>
                <input type="number" name="affected_quantity" min="1" value="1" class="mt-1">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700">Description &amp; Details *</label>
                <textarea name="description" rows="3" required placeholder="Describe what happened, when you noticed it, and any affected gear or accounts..." class="mt-1"></textarea>
            </div>

            <button type="submit" class="btn-maroon w-full !py-2.5">
                Submit Incident Report
            </button>
        </form>
    </div>

    <!-- Incident Lifecycle List -->
    <div class="space-y-4 lg:col-span-8">
        <form method="GET" action="{{ route('incidents.index') }}" class="card-surface flex flex-wrap gap-2 p-3.5">
            <select name="category" class="w-44 !py-1.5 !text-xs">
                <option value="">All Categories</option>
                <option value="equipment" @selected(request('category') === 'equipment')>Equipment</option>
                <option value="security" @selected(request('category') === 'security')>Security</option>
            </select>
            <select name="status" class="w-44 !py-1.5 !text-xs">
                <option value="">All Response Stages</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-maroon !py-1.5">Filter</button>
        </form>

        @if ($incidents->isEmpty())
            <x-empty-state title="No incidents reported" message="Reported equipment damage, missing gear, and security alerts appear here." icon="alert" />
        @else
            <div class="space-y-3">
                @foreach ($incidents as $inc)
                    <div class="card-surface p-5 text-xs transition hover:border-maroon-200">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono font-bold text-maroon-900">{{ $inc->report_code }}</span>
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-700 uppercase">{{ $inc->category }}</span>
                                <span class="font-semibold text-charcoal">{{ $types[$inc->incident_type] ?? $inc->incident_type }}</span>
                                <x-status-badge :status="$inc->status" />
                            </div>
                            <span class="text-[11px] text-gray-500">By {{ $inc->reporter?->name }} · {{ $inc->created_at?->format('M d, Y h:i A') }}</span>
                        </div>

                        @if ($inc->inventoryItem)
                            <p class="mt-2 text-gray-700">
                                <strong>Item:</strong>
                                <a href="{{ route('inventory.show', $inc->inventoryItem) }}" class="font-semibold text-maroon-900 hover:underline">
                                    {{ $inc->inventoryItem->property_code }} — {{ $inc->inventoryItem->name }}
                                </a>
                                (Affected qty: {{ $inc->affected_quantity }})
                            </p>
                        @endif

                        <p class="mt-1.5 text-gray-700">{{ $inc->description }}</p>

                        @if ($inc->resolution_notes || $inc->lessons_learned)
                            <div class="mt-3 space-y-1 rounded-xl bg-gray-50 p-3 text-gray-700">
                                @if ($inc->resolution_notes)
                                    <p><strong>Response Action ({{ $inc->resolver?->name ?? 'Officer' }}):</strong> {{ $inc->resolution_notes }}</p>
                                @endif
                                @if ($inc->lessons_learned)
                                    <p><strong>Lessons Learned:</strong> {{ $inc->lessons_learned }}</p>
                                @endif
                            </div>
                        @endif

                        @if ($canResolve)
                            <form method="POST" action="{{ route('incidents.resolve', $inc) }}" class="mt-3 grid gap-2.5 rounded-xl border border-gray-200 bg-gray-50 p-3 sm:grid-cols-12">
                                @csrf
                                <div class="sm:col-span-3">
                                    <select name="status" class="!py-1.5 !text-xs">
                                        @foreach ($statuses as $key => $label)
                                            <option value="{{ $key }}" @selected($inc->status === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="sm:col-span-4">
                                    <input type="text" name="resolution_notes" value="{{ $inc->resolution_notes }}" required placeholder="Containment / resolution notes *" class="!py-1.5 !text-xs">
                                </div>
                                <div class="sm:col-span-3">
                                    <input type="text" name="lessons_learned" value="{{ $inc->lessons_learned }}" placeholder="Lessons learned (optional)" class="!py-1.5 !text-xs">
                                </div>
                                <div class="sm:col-span-2 flex items-center justify-end gap-2">
                                    @if ($inc->category === 'equipment' && $inc->inventoryItem)
                                        <label class="inline-flex items-center gap-1 text-[10px]" title="Deduct affected stock">
                                            <input type="checkbox" name="apply_stock_movement" value="1">
                                            <span>Deduct</span>
                                        </label>
                                    @endif
                                    <button type="submit" class="rounded-lg bg-maroon-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-maroon-800">Save</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
            <div>{{ $incidents->links() }}</div>
        @endif
    </div>
</div>
@endsection
