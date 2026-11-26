@props(['name', 'title' => '', 'maxWidth' => 'lg'])

@php
    $widths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ];
    $widthClass = $widths[$maxWidth] ?? $widths['lg'];
@endphp

<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs" x-on:click="open = false"></div>

    <div
        x-show="open"
        x-transition
        class="relative mx-auto mt-10 w-full {{ $widthClass }} overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-gray-200"
    >
        @if ($title)
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h3 class="text-base font-semibold text-charcoal">{{ $title }}</h3>
                <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                    <x-icon name="x" class="w-5 h-5" />
                </button>
            </div>
        @endif
        <div class="p-6">
            {{ $slot }}
        </div>
    </div>
</div>
