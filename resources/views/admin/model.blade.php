@extends('layouts.app')

@section('title', 'Model prediksi')

@section('content')
<h1 class="rise text-3xl sm:text-4xl">Model prediksi</h1>
<p class="rise mt-2 max-w-[64ch] text-ink-2" style="--i: 1">Admin melatih Random Forest dan XGBoost (dijelaskan dengan SHAP) pada data latih, memilih yang terbaik lewat validasi silang, lalu menguji satu kali pada data uji terpisah. Model aturan berbobot dilatih pada data yang sama sebagai pembanding dan cadangan. Versi baru hanya bisa diaktifkan bila macro-F1 minimal {{ \App\Support\Fmt::num($targetF1) }} dan recall kelas Tinggi minimal {{ \App\Support\Fmt::num($targetRecall) }}.</p>

<section class="mt-10 max-w-xl" aria-labelledby="latih">
    <h2 id="latih" class="text-xl">Latih versi baru</h2>
    <form method="POST" action="{{ route('admin.model.latih') }}" class="mt-4 flex flex-wrap items-end gap-3">
        @csrf
        <div class="min-w-64 flex-1">
            <label for="dataset_id" class="label">Dataset historis</label>
            <select id="dataset_id" name="dataset_id" class="input" required>
                <option value="">Pilih dataset</option>
                @foreach ($datasets as $d)
                    <option value="{{ $d->id }}">{{ $d->name }} ({{ $d->row_count }} baris)</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><x-icon name="brain" /> Latih dan uji</button>
    </form>
    @error('dataset_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    @if ($datasets->isEmpty())
        <p class="mt-3 text-sm text-ink-2">Belum ada dataset. <a href="{{ route('admin.historis') }}" class="font-medium text-accent hover:underline">Unggah data historis</a> dulu.</p>
    @endif
</section>

<section class="mt-14 border-t border-line pt-8" aria-labelledby="versi">
    <h2 id="versi" class="text-xl">Versi model</h2>
    <div class="mt-4 overflow-x-auto">
        <table class="tbl min-w-[56rem]">
            <thead><tr><th>Versi</th><th>Algoritma</th><th>Ambang sedang / tinggi</th><th>Macro-F1</th><th>Recall Tinggi</th><th>Data latih / uji</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @foreach ($versions as $v)
                    <tr>
                        <td><span class="mono font-medium">{{ $v->version }}</span><span class="block text-sm text-ink-3">{{ $v->trained_at?->translatedFormat('j F Y') }}</span></td>
                        <td class="text-sm text-ink-2">{{ $v->algorithm }}</td>
                        <td class="num mono">{{ \App\Support\Fmt::num($v->params['sedang'] ?? 0) }} / {{ \App\Support\Fmt::num($v->params['tinggi'] ?? 0) }}</td>
                        <td class="num mono">{{ $v->macro_f1 !== null ? \App\Support\Fmt::num($v->macro_f1, 3) : '-' }}</td>
                        <td class="num mono">{{ $v->recall_high !== null ? \App\Support\Fmt::num($v->recall_high, 3) : '-' }}</td>
                        <td class="num">{{ $v->train_rows }} / {{ $v->test_rows }}</td>
                        <td>
                            @if ($v->is_active) <span class="risk risk-chip risk-rendah"><x-icon name="check-circle" /> Aktif</span>
                            @elseif ($v->meets_target) <span class="text-ink-2">Memenuhi target</span>
                            @else <span class="text-ink-3">Di bawah target</span> @endif
                        </td>
                        <td class="text-right">
                            @if (! $v->is_active && $v->meets_target)
                                <form method="POST" action="{{ route('admin.model.aktifkan', $v) }}">@csrf<button class="btn btn-quiet btn-sm" type="submit">Aktifkan</button></form>
                            @endif
                        </td>
                    </tr>
                    @if (! empty($v->metrics['confusion']))
                        <tr>
                            <td colspan="8" class="!pt-0">
                                <details class="text-sm">
                                    <summary class="cursor-pointer text-accent hover:underline">Matriks kebingungan data uji{{ isset($v->metrics['baseline']) ? ' dan pembanding model aturan' : '' }}</summary>
                                    @if (isset($v->metrics['baseline']))
                                        <p class="mt-3 text-ink-2">Pembanding model aturan pada data uji yang sama: macro-F1 <span class="mono">{{ \App\Support\Fmt::num($v->metrics['baseline']['macro_f1'], 3) }}</span>, recall Tinggi <span class="mono">{{ \App\Support\Fmt::num($v->metrics['baseline']['recall_high'], 3) }}</span>.</p>
                                    @endif
                                    @if (! empty($v->metrics['candidates']))
                                        <p class="mt-1 text-ink-2">Kandidat:
                                            @foreach ($v->metrics['candidates'] as $name => $c)
                                                {{ $name === 'xgboost' ? 'XGBoost' : 'Random Forest' }} (macro-F1 uji {{ \App\Support\Fmt::num($c['test_macro_f1'], 3) }}, recall Tinggi {{ \App\Support\Fmt::num($c['test_recall_high'], 3) }})@if (! $loop->last); @endif
                                            @endforeach
                                        </p>
                                    @endif
                                    <table class="tbl mt-3 max-w-md">
                                        <thead><tr><th>Sebenarnya \ Prediksi</th><th>Rendah</th><th>Sedang</th><th>Tinggi</th></tr></thead>
                                        <tbody>
                                            @foreach (['rendah', 'sedang', 'tinggi'] as $a)
                                                <tr><td class="capitalize">{{ $a }}</td>@foreach (['rendah', 'sedang', 'tinggi'] as $p)<td class="num">{{ $v->metrics['confusion'][$a][$p] ?? 0 }}</td>@endforeach</tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </details>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-4 max-w-[64ch] text-sm text-ink-3">Setelah mengaktifkan versi baru, tombol Hitung ulang di Ringkasan risiko mahasiswa memakai model barunya. Random Forest dan XGBoost berjalan di layanan model Python (folder <span class="mono">ml-service</span>). Hubungkan lewat <span class="mono">RISK_ENGINE=http</span> pada berkas .env. Bila layanan tidak menjawab, model aturan dipakai otomatis.</p>
</section>
@endsection
