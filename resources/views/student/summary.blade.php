@extends('layouts.app')

@section('title', 'Ringkasan risiko')

@php
    $done = array_values(array_filter([
        $hasData ? 'impor' : null,
        $latest ? 'risiko' : null,
        $consent ? 'privasi' : null,
    ]));
    $thresholds = $version?->params ?? \App\Support\RiskModel::DEFAULT_THRESHOLDS;
@endphp

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="rise text-3xl sm:text-4xl">Ringkasan risiko</h1>
        <p class="rise mt-2 text-ink-2" style="--i: 1">Halo, {{ strtok($user->name, ' ') }}. Ini posisi akademikmu menurut data yang kamu impor.</p>
    </div>
    @if ($latest)
        <form method="POST" action="{{ route('mhs.hitung') }}" class="no-print">
            @csrf
            <button class="btn btn-quiet btn-sm" type="submit">Hitung ulang</button>
        </form>
    @endif
</div>

<div class="mt-8">
    <x-flow :done="$done" :current="! $hasData ? 'impor' : (! $consent ? 'privasi' : 'risiko')" />
</div>

@if (! $hasData)
    <section class="rise mt-12 max-w-2xl rounded-md border border-dashed border-line-strong p-8" style="--i: 2">
        <h2 class="text-2xl">Belum ada data akademik.</h2>
        <p class="mt-3 text-ink-2">Unduh berkas KRS, nilai, dan absensi dari portal akademik atau sekretariat, lalu unggah di sini. Sistem menghitung risikomu begitu berkasnya lolos pemeriksaan.</p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('mhs.impor') }}" class="btn btn-primary"><x-icon name="upload-simple" /> Impor data</a>
            <a href="{{ route('mhs.templat') }}" class="btn btn-quiet"><x-icon name="download-simple" /> Unduh templat</a>
        </div>
    </section>
@else
    <div class="mt-12 grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <x-risk-panel :result="$latest" :thresholds="$thresholds" :version="$version?->version" />
        </div>

        <aside class="space-y-10 lg:col-span-4">
            <section aria-labelledby="per-semester">
                <h2 id="per-semester" class="text-xl">Per semester</h2>
                @php
                    $ipsSeries = $results->map(fn ($r) => (float) ($r->features['ips'] ?? 0))->values();
                    $n = $ipsSeries->count();
                    $w = 260; $h = 64; $pad = 6;
                    $lo = max(0, ($ipsSeries->min() ?? 0) - 0.4);
                    $hi = min(4, ($ipsSeries->max() ?? 4) + 0.4);
                    $span = max(0.5, $hi - $lo);
                    $pts = $ipsSeries->map(fn ($v, $i) => [
                        $pad + ($n > 1 ? $i * ($w - 2 * $pad) / ($n - 1) : ($w - 2 * $pad) / 2),
                        $pad + (1 - ($v - $lo) / $span) * ($h - 2 * $pad),
                    ]);
                @endphp
                @if ($n > 1)
                    <svg viewBox="0 0 {{ $w }} {{ $h }}" class="mt-3 w-full text-accent" role="img" aria-label="IPS per semester dari {{ \App\Support\Fmt::num($ipsSeries->first()) }} sampai {{ \App\Support\Fmt::num($ipsSeries->last()) }}">
                        <polyline fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" points="{{ $pts->map(fn ($p) => round($p[0], 1).','.round($p[1], 1))->implode(' ') }}" />
                        @foreach ($pts as $p)
                            <circle cx="{{ round($p[0], 1) }}" cy="{{ round($p[1], 1) }}" r="3" fill="currentColor" />
                        @endforeach
                    </svg>
                    <p class="text-xs text-ink-3">IPS tiap semester. Sumbu vertikal menyesuaikan rentang datamu.</p>
                @endif
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
                <a href="{{ route('mhs.riwayat') }}" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-accent hover:underline">Riwayat lengkap <x-icon name="arrow-right" size="size-4" /></a>
            </section>

            <section aria-labelledby="status-dpa" class="border-t border-line pt-8">
                <h2 id="status-dpa" class="text-xl">Izin untuk DPA</h2>
                @if (! $dpa)
                    <p class="mt-3 text-ink-2">Belum ada DPA yang ditetapkan untukmu. Sekretariat bisa mengaturnya.</p>
                @else
                    <p class="mt-3">{{ $dpa->name }}</p>
                    <p class="mt-1 text-ink-2">
                        @if ($consent?->granted)
                            Boleh melihat hasilmu sejak {{ $consent->changed_at->translatedFormat('j F Y') }}.
                        @elseif ($consent)
                            Izin dicabut pada {{ $consent->changed_at->translatedFormat('j F Y') }}. DPA tidak bisa melihat hasilmu.
                        @else
                            Belum diizinkan. DPA tidak bisa melihat hasilmu.
                        @endif
                    </p>
                    <a href="{{ route('mhs.privasi') }}" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-accent hover:underline">Atur izin <x-icon name="arrow-right" size="size-4" /></a>
                @endif
            </section>

            <section aria-labelledby="pengingat" class="border-t border-line pt-8">
                <h2 id="pengingat" class="text-xl">Pengingat</h2>
                @forelse ($reminders as $rem)
                    <div class="mt-4">
                        <p class="text-sm text-ink-3">
                            {{ $rem->created_at->translatedFormat('j F Y, H:i') }}
                            @if (in_array($rem->id, $unreadIds, true)) <span class="ml-1 font-medium text-accent">Baru</span> @endif
                        </p>
                        <p class="mt-0.5">{{ $rem->message }}</p>
                    </div>
                @empty
                    <p class="mt-3 text-ink-2">Belum ada pengingat. Pengingat muncul saat risikomu sedang atau tinggi.</p>
                @endforelse
            </section>
        </aside>
    </div>
@endif
@endsection
