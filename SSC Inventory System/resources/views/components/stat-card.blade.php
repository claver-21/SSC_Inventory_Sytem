@props([
    'title',
    'value',
    'subtitle' => null,
    'icon' => 'inventory',
    'tone' => 'maroon',
    'href' => null,
])

@php
    $tones = [
        'maroon' => ['icon' => 'bg-maroon-50 text-maroon-900 ring-maroon-200/80', 'bar' => 'from-maroon-800 to-maroon-600'],
        'emerald' => ['icon' => 'bg-emerald-50 text-emerald-700 ring-emerald-200/80', 'bar' => 'from-emerald-600 to-emerald-400'],
        'blue' => ['icon' => 'bg-sky-50 text-sky-700 ring-sky-200/80', 'bar' => 'from-sky-600 to-sky-400'],
        'amber' => ['icon' => 'bg-amber-50 text-amber-700 ring-amber-200/80', 'bar' => 'from-amber-600 to-amber-400'],
        'red' => ['icon' => 'bg-red-50 text-red-700 ring-red-200/80', 'bar' => 'from-red-600 to-red-400'],
        'orange' => ['icon' => 'bg-orange-50 text-orange-700 ring-orange-200/80', 'bar' => 'from-orange-600 to-orange-400'],
        'slate' => ['icon' => 'bg-slate-100 text-slate-700 ring-slate-200/80', 'bar' => 'from-slate-600 to-slate-400'],
    ];
    $style = $tones[$tone] ?? $tones['maroon'];
@endphp

<div class="group relative overflow-hidden rounded-2xl border border-gray-200/90 bg-white p-5 shadow-[0_2px_14px_rgba(37,16,24,0.045)] transition duration-200 hover:-translate-y-0.5 hover:border-maroon-200 hover:shadow-[0_10px_24px_rgba(101,13,27,0.08)]">
    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $style['bar'] }} opacity-80"></div>
    <div class="flex items-start justify-between gap-3 pt-0.5">
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-bold tracking-wider text-gray-500 uppercase">{{ $title }}</p>
            <p class="mt-2 text-3xl font-extrabold tracking-tight text-charcoal tabular-nums">{{ $value }}</p>
            @if ($subtitle)
                <p class="mt-1 truncate text-xs text-gray-500">{{ $subtitle }}</p>
            @endif
        </div>
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ring-1 transition duration-200 group-hover:scale-105 {{ $style['icon'] }}">
            <x-icon :name="$icon" class="w-5 h-5" />
        </div>
    </div>
    @if ($href)
        <div class="mt-4 border-t border-gray-100 pt-2.5">
            <a href="{{ $href }}" class="inline-flex items-center gap-1 text-xs font-semibold text-maroon-800 transition group-hover:text-maroon-950">
                View records
                <span class="transition-transform duration-200 group-hover:translate-x-0.5" aria-hidden="true">&rarr;</span>
            </a>
        </div>
    @endif
</div>
