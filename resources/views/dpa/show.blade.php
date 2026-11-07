@extends('layouts.app')

@section('title', $student->name)

@section('content')
<a href="{{ route('dpa.bimbingan') }}" class="no-print inline-flex items-center gap-1 text-sm font-medium text-accent hover:underline"><x-icon name="arrow-right" class="rotate-180" size="size-4" /> Daftar bimbingan</a>
<h1 class="rise mt-4 text-3xl sm:text-4xl">{{ $student->name }}</h1>
<p class="rise mono mt-1 text-ink-2" style="--i: 1">{{ $student->username }}</p>

@if (! $latest)
    <p class="mt-10 max-w-xl text-ink-2">Mahasiswa ini belum mengimpor data, jadi belum ada hasil risiko.</p>
@else
    @php $version = $latest->modelVersion; $thresholds = $version?->params ?? \App\Support\RiskModel::DEFAULT_THRESHOLDS; @endphp
    <div class="mt-10 grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <x-risk-panel :result="$latest" :thresholds="$thresholds" :version="$version?->version" audience="dpa" />
        </div>
        <aside class="lg:col-span-4">
            <h2 class="text-xl">Per semester</h2>
            <ul class="mt-4 divide-y divide-line">
                @foreach ($results->sortByDesc('semester') as $r)
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <span class="num">Semester {{ $r->semester }}</span>
                        <span class="flex items-center gap-3">
                            <span class="mono num text-ink-2">{{ \App\Support\Fmt::num($r->features['ips'] ?? 0) }}</span>
                            <x-risk :level="$r->level" />
                        </span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-6 text-sm text-ink-3">Kamu melihat halaman ini karena mahasiswa memberi izin. Izin bisa dicabut kapan saja dan akses ini tercatat.</p>
        </aside>
    </div>
@endif
@endsection
