@extends('layouts.app')

@section('page_title', 'System Settings')
@section('page_subtitle', 'Configure institutional parameters and borrowing defaults')

@section('content')
<form method="POST" action="{{ route('settings.update') }}" class="mx-auto max-w-4xl space-y-6">
    @csrf
    @method('PUT')

    @foreach ($groupedSettings as $group => $items)
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
            <h2 class="border-b border-gray-100 pb-3 text-sm font-bold tracking-wide text-maroon-900 uppercase">
                {{ str_replace('_', ' ', $group) }} Settings
            </h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($items as $setting)
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">{{ $setting->label }}</label>
                        <input
                            type="{{ $setting->type === 'integer' ? 'number' : 'text' }}"
                            name="settings[{{ $setting->key }}]"
                            value="{{ old('settings.' . $setting->key, $setting->value) }}"
                            class="mt-1"
                        >
                        @if ($setting->description)
                            <p class="mt-1 text-[11px] text-gray-400">{{ $setting->description }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="flex justify-end">
        <button type="submit" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-maroon-800">
            Save System Settings
        </button>
    </div>
</form>
@endsection
