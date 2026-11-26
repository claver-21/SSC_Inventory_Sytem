@props(['condition'])

@php
    $slug = strtolower((string) ($condition?->slug ?? $condition ?? 'good'));
    $name = $condition?->name ?? ucfirst(str_replace('_', ' ', $slug));
    $colors = [
        'excellent' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'good' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'fair' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'poor' => 'bg-orange-50 text-orange-800 ring-orange-200',
        'non_functional' => 'bg-red-50 text-red-700 ring-red-200',
    ];
    $classes = $colors[$slug] ?? 'bg-gray-100 text-gray-700 ring-gray-200';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset ' . $classes]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
    {{ $name }}
</span>
