@extends('layouts.app')

@section('title', 'Riwayat semester')

@section('content')
<h1 class="rise text-3xl sm:text-4xl">Riwayat semester</h1>
<p class="rise mt-2 max-w-[60ch] text-ink-2" style="--i: 1">Hasil tiap semester disimpan lengkap dengan tanggal data dan versi model yang dipakai.</p>

@if ($results->isEmpty())
    <section class="mt-10 max-w-2xl rounded-md border border-dashed border-line-strong p-8">
        <h2 class="text-2xl">Belum ada riwayat.</h2>
        <p class="mt-3 text-ink-2">Riwayat terbentuk setelah kamu mengimpor data akademik.</p>
        <a href="{{ route('mhs.impor') }}" class="btn btn-primary mt-6"><x-icon name="upload-simple" /> Impor data</a>
    </section>
@else
    @php
        $sortedResults = $results->sortBy('semester')->values();
        $ipsSeries = $sortedResults->map(fn ($r) => (float) ($r->features['ips'] ?? 0))->values();
        $n = $ipsSeries->count();
        $w = 520; $h = 120; $pad = 12;
        $lo = max(0, ($ipsSeries->min() ?? 0) - 0.4);
        $hi = min(4, ($ipsSeries->max() ?? 4) + 0.4);
        $span = max(0.5, $hi - $lo);
        $pts = $ipsSeries->map(fn ($v, $i) => [
            $pad + ($n > 1 ? $i * ($w - 2 * $pad) / ($n - 1) : ($w - 2 * $pad) / 2),
            $pad + (1 - ($v - $lo) / $span) * ($h - 2 * $pad),
        ]);
    @endphp

    @if ($n > 0)
        <section class="mt-8 max-w-xl rounded-md border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-lg font-medium">Grafik Perkembangan IPS per Semester</h2>
            <div class="mt-4">
                <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full text-accent" role="img" aria-label="Grafik IPS per semester">
                    @if ($n > 1)
                        <polyline fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" points="{{ $pts->map(fn ($p) => round($p[0], 1).','.round($p[1], 1))->implode(' ') }}" />
                    @endif
                    @foreach ($pts as $i => $p)
                        <circle cx="{{ round($p[0], 1) }}" cy="{{ round($p[1], 1) }}" r="4.5" fill="currentColor" />
                        <text x="{{ round($p[0], 1) }}" y="{{ round($p[1], 1) - 10 }}" text-anchor="middle" class="text-xs fill-current font-mono">{{ \App\Support\Fmt::num($ipsSeries[$i]) }}</text>
                        <text x="{{ round($p[0], 1) }}" y="{{ $h - 2 }}" text-anchor="middle" class="text-xs fill-ink-3">Sem {{ $sortedResults[$i]->semester }}</text>
                    @endforeach
                </svg>
            </div>
        </section>
    @endif

    <div class="mt-10 overflow-x-auto">
        <table class="tbl min-w-[48rem]">
            <thead>
                <tr><th>Semester</th><th>SKS</th><th>IPS</th><th>Kehadiran</th><th>Risiko</th><th>Skor</th><th>Data per</th><th>Model</th><th><span class="sr-only">Aksi</span></th></tr>
            </thead>
            <tbody>
                @foreach ($results as $r)
                    <tr>
                        <td class="num font-medium">{{ $r->semester }}</td>
                        <td class="num">{{ $r->features['credits'] ?? '-' }}</td>
                        <td class="num mono">{{ \App\Support\Fmt::num($r->features['ips'] ?? 0) }}</td>
                        <td class="num">{{ \App\Support\Fmt::pct($r->features['attendance'] ?? 0) }}</td>
                        <td><x-risk :level="$r->level" /></td>
                        <td class="num mono">{{ \App\Support\Fmt::num($r->score, 1) }}</td>
                        <td class="num whitespace-nowrap">{{ $r->data_as_of?->translatedFormat('j M Y') }}</td>
                        <td class="mono">{{ $r->modelVersion?->version ?? '-' }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('mhs.riwayat.hapus', $r->semester) }}" data-confirm="Hapus data semester {{ $r->semester }}?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection