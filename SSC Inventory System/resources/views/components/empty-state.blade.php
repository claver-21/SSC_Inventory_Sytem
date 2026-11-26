@props([
    'title' => 'No records found',
    'message' => 'There are currently no matching records to display.',
    'icon' => 'inventory',
    'actionLabel' => null,
    'actionHref' => null,
])

<div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-maroon-200/80 bg-white px-6 py-14 text-center shadow-[0_2px_14px_rgba(37,16,24,0.03)]">
    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-maroon-50 text-maroon-800 ring-4 ring-maroon-50/70">
        <x-icon :name="$icon" class="w-6 h-6" />
    </div>
    <h3 class="mt-4 text-sm font-bold text-charcoal">{{ $title }}</h3>
    <p class="mt-1 max-w-md text-xs leading-relaxed text-gray-500">{{ $message }}</p>
    @if ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}" class="btn-maroon mt-5">
            <x-icon name="plus" class="w-4 h-4" />
            {{ $actionLabel }}
        </a>
    @endif
</div>
