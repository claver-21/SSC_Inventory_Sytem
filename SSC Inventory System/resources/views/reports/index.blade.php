@extends('layouts.app')

@section('page_title', 'Reports & Document Generation')
@section('page_subtitle', 'Generate printable and downloadable PDF, Excel (.xlsx), and CSV institutional inventory reports')

@section('content')
<div class="space-y-5">
    <div class="card-surface border-maroon-100 p-5">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/pcb-ssc-logo.png') }}" alt="PCB SSC Seal" class="h-10 w-10 rounded-full border border-gray-200 object-contain p-0.5">
            <div>
                <p class="text-xs font-bold tracking-wider text-maroon-900 uppercase">Polytechnic College of Botolan — Supreme Student Council</p>
                <p class="text-xs text-gray-500">All generated documents carry official council letterhead and generation metadata.</p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($reports as $slug => $meta)
            <div class="card-surface flex flex-col justify-between p-5 transition duration-200 hover:-translate-y-0.5 hover:border-maroon-200 hover:shadow-md">
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-maroon-50 text-maroon-900">
                            <x-icon name="reports" class="w-5 h-5" />
                        </span>
                        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-semibold text-gray-600 uppercase">
                            PDF · XLSX · CSV
                        </span>
                    </div>
                    <h2 class="mt-3 text-sm font-bold text-charcoal">{{ $meta['title'] }}</h2>
                    <p class="mt-1 text-xs leading-relaxed text-gray-500">{{ $meta['description'] }}</p>
                </div>

                <div class="mt-4 border-t border-gray-100 pt-3 flex items-center justify-between gap-2">
                    <a href="{{ route('reports.show', $slug) }}" class="inline-flex items-center gap-1 text-xs font-bold text-maroon-900 hover:underline">
                        Open &amp; Filter &rarr;
                    </a>
                    @permission('reports.export')
                        <div class="flex gap-1.5">
                            <a href="{{ route('reports.pdf', $slug) }}" class="rounded-lg border border-red-200 bg-red-50 px-2 py-1 text-[10px] font-bold text-red-800 hover:bg-red-100">PDF</a>
                            <a href="{{ route('reports.excel', $slug) }}" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-800 hover:bg-emerald-100">XLSX</a>
                            <a href="{{ route('reports.csv', $slug) }}" class="rounded-lg border border-gray-200 bg-gray-50 px-2 py-1 text-[10px] font-bold text-gray-700 hover:bg-gray-100">CSV</a>
                        </div>
                    @endpermission
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
