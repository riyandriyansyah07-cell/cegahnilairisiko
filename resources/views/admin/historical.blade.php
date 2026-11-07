@extends('layouts.app')

@section('title', 'Data historis')

@section('content')
<h1 class="rise text-3xl sm:text-4xl">Data historis anonim</h1>
<p class="rise mt-2 max-w-[62ch] text-ink-2" style="--i: 1">Bahan untuk melatih dan menguji model. Berkas tidak boleh memuat nama, NIM, atau email. Kolom identitas membuat berkas ditolak.</p>

<div class="mt-10">@include('partials.import-errors')</div>

<div class="grid gap-12 lg:grid-cols-12">
    <section class="lg:col-span-5">
        <h2 class="text-xl">Unggah dataset</h2>
        <form method="POST" action="{{ route('admin.historis.store') }}" enctype="multipart/form-data" class="mt-4">
            @csrf
            <div data-dropzone class="rounded-md border border-dashed border-line-strong p-6 transition-colors data-[over]:border-accent data-[over]:bg-accent-soft">
                <label for="file" class="label">Berkas CSV atau XLSX</label>
                <input id="file" name="file" type="file" accept=".csv,.txt,.xlsx" required
                       class="block w-full text-sm file:mr-4 file:h-9 file:cursor-pointer file:rounded-md file:border file:border-line-strong file:bg-surface file:px-3 file:text-ink hover:file:bg-sunken">
                @error('file')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <p class="mt-3 text-sm text-ink-2">Kolom: <span class="mono">kehadiran, ips, rasio_sks_tidak_lulus, perubahan_ips, label</span>. Label berisi rendah, sedang, atau tinggi. Minimal 100 baris, maksimal 5.000.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <button class="btn btn-primary" type="submit"><x-icon name="file-arrow-up" /> Unggah</button>
                <a href="{{ route('admin.historis.templat', ['format' => 'xlsx']) }}" class="btn btn-quiet"><x-icon name="download-simple" /> Templat XLSX</a>
                <a href="{{ route('admin.historis.templat', ['format' => 'csv']) }}" class="btn btn-quiet"><x-icon name="download-simple" /> Templat CSV</a>
            </div>
        </form>
    </section>

    <section class="lg:col-span-7">
        <h2 class="text-xl">Dataset tersimpan</h2>
        @if ($datasets->isEmpty())
            <p class="mt-4 text-ink-2">Belum ada dataset. Unggah satu untuk bisa melatih model.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="tbl min-w-[34rem]">
                    <thead><tr><th>Berkas</th><th>Baris</th><th>Rendah</th><th>Sedang</th><th>Tinggi</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                        @foreach ($datasets as $d)
                            <tr>
                                <td><span class="mono break-all">{{ $d->name }}</span><span class="block text-sm text-ink-3">{{ $d->created_at->translatedFormat('j F Y') }}</span></td>
                                <td class="num">{{ \App\Support\Fmt::num($d->row_count, 0) }}</td>
                                <td class="num">{{ $d->label_counts['rendah'] ?? 0 }}</td>
                                <td class="num">{{ $d->label_counts['sedang'] ?? 0 }}</td>
                                <td class="num">{{ $d->label_counts['tinggi'] ?? 0 }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('admin.historis.hapus', $d) }}" data-confirm="Hapus dataset ini? Versi model yang sudah dilatih tetap tersimpan.">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
