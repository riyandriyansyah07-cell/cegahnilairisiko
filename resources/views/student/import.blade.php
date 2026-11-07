@extends('layouts.app')

@section('title', 'Impor data')

@section('content')
<h1 class="rise text-3xl sm:text-4xl">Impor data akademik</h1>
<p class="rise mt-2 max-w-[60ch] text-ink-2" style="--i: 1">Unggah berkas KRS, nilai, dan absensi dari portal akademik. NIM-mu disamarkan sebelum disimpan dan berkas mentahnya tidak disimpan sama sekali.</p>

<div class="mt-8"><x-flow :current="'impor'" /></div>

<div class="mt-10">@include('partials.import-errors')</div>

<div class="grid gap-12 lg:grid-cols-12">
    <section class="lg:col-span-6">
        <h2 class="text-xl">Pilih berkas</h2>
        <form method="POST" action="{{ route('mhs.impor.store') }}" enctype="multipart/form-data" class="mt-4">
            @csrf
            <div data-dropzone class="rounded-md border border-dashed border-line-strong p-6 transition-colors data-[over]:border-accent data-[over]:bg-accent-soft">
                <label for="file" class="label">Berkas CSV, TXT, XLSX, atau PDF</label>
            <input id="file" name="file" type="file" accept=".csv,.txt,.xlsx,.pdf" required
                class="block w-full text-sm file:mr-4 file:h-9 file:cursor-pointer file:rounded-md file:border file:border-line-strong file:bg-surface file:px-3 file:text-ink hover:file:bg-sunken">
                @error('file')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <ul class="mt-4 space-y-1 text-sm text-ink-2">
                <li>Ukuran maksimal {{ number_format($maxKb / 1024, 0) }} MB, paling banyak 500 baris.</li>
                <li>Satu kesalahan membatalkan seluruh impor, supaya datamu tidak setengah masuk.</li>
                <li>Impor ulang untuk semester yang sama menimpa data semester itu.</li>
            </ul>
            <div class="mt-6 flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary"><x-icon name="file-arrow-up" /> Impor dan hitung risiko</button>
                <a href="{{ route('mhs.templat', ['format' => 'xlsx']) }}" class="btn btn-quiet"><x-icon name="download-simple" /> Unduh templat XLSX</a>
                <a href="{{ route('mhs.templat', ['format' => 'csv']) }}" class="btn btn-quiet"><x-icon name="download-simple" /> Unduh templat CSV</a>
            </div>
        </form>
    </section>

    <section class="lg:col-span-6">
        <h2 class="text-xl">Format kolom</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="tbl min-w-[28rem]">
                <thead><tr><th>Kolom</th><th>Isi</th><th>Contoh</th></tr></thead>
                <tbody>
                    <tr><td class="mono">semester</td><td>Angka 1 sampai 14</td><td class="mono">6</td></tr>
                    <tr><td class="mono">kode_mk</td><td>Kode mata kuliah</td><td class="mono">IF601</td></tr>
                    <tr><td class="mono">nama_mk</td><td>Nama mata kuliah</td><td>Basis Data Lanjut</td></tr>
                    <tr><td class="mono">sks</td><td>Angka 1 sampai 6</td><td class="mono">3</td></tr>
                    <tr><td class="mono">nilai</td><td>Huruf A sampai E, atau angka 0 sampai 100</td><td class="mono">B+ atau 78</td></tr>
                    <tr><td class="mono">kehadiran_persen</td><td>Persentase 0 sampai 100</td><td class="mono">86</td></tr>
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-sm text-ink-2">Kolom <span class="mono">nim</span> boleh ada. Isinya harus NIM akunmu, dan tidak disimpan. Pemisah koma atau titik koma dikenali otomatis.</p>
    </section>
</div>

@if ($batches->isNotEmpty())
    <section class="mt-14 border-t border-line pt-8">
        <h2 class="text-xl">Impor terakhir</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="tbl min-w-[32rem]">
                <thead><tr><th>Waktu</th><th>Berkas</th><th>Baris</th><th>Semester</th></tr></thead>
                <tbody>
                    @foreach ($batches as $b)
                        <tr>
                            <td class="num">{{ $b->created_at->translatedFormat('j F Y, H:i') }}</td>
                            <td class="mono break-all">{{ $b->source_name }}</td>
                            <td class="num">{{ $b->rows_valid }}</td>
                            <td class="num">{{ implode(', ', $b->semesters) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
@endsection
