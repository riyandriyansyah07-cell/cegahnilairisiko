@extends('layouts.app')

@section('title', 'Mahasiswa bimbingan')

@section('content')
<h1 class="rise text-3xl sm:text-4xl">Mahasiswa bimbingan</h1>
<p class="rise mt-2 max-w-[62ch] text-ink-2" style="--i: 1">Hanya mahasiswa yang sudah memberi izin yang tampil. Setiap tampilan tercatat di log akses.</p>

@if ($pending > 0)
    <p class="mt-6 flex max-w-2xl items-start gap-3 rounded-md border border-line-strong bg-surface px-4 py-3 text-ink-2">
        <x-icon name="lock-key" class="mt-0.5" />
        <span>{{ $pending }} dari {{ $total }} mahasiswa bimbingan belum memberi izin. Namanya tidak ditampilkan.</span>
    </p>
@endif

@if ($counts['semua'] === 0)
    <section class="mt-10 max-w-2xl rounded-md border border-dashed border-line-strong p-8">
        <h2 class="text-2xl">Belum ada yang menyetujui.</h2>
        <p class="mt-3 text-ink-2">Daftar terisi saat mahasiswa bimbinganmu mengizinkan DPA melihat hasil risikonya. Kamu bisa mengingatkan mereka lewat jalur biasa.</p>
    </section>
@else
    <nav class="mt-10 flex flex-wrap gap-2" aria-label="Saring menurut tingkat risiko">
        @foreach ([null => 'Semua', 'tinggi' => 'Tinggi', 'sedang' => 'Sedang', 'rendah' => 'Rendah', 'belum' => 'Belum dihitung'] as $key => $label)
            @php $active = ($filter === ($key ?: null)); $count = $counts[$key ?: 'semua']; @endphp
            <a href="{{ $key ? route('dpa.bimbingan', ['tingkat' => $key]) : route('dpa.bimbingan') }}"
               @if ($active) aria-current="true" @endif
               class="btn btn-sm {{ $active ? 'btn-primary' : 'btn-quiet' }}">{{ $label }} <span class="num opacity-80">{{ $count }}</span></a>
        @endforeach
    </nav>

    @if ($rows->isEmpty())
        <p class="mt-8 text-ink-2">Tidak ada mahasiswa pada tingkat ini.</p>
    @else
        <div class="mt-6 overflow-x-auto">
            <table class="tbl min-w-[42rem]">
                <thead><tr><th>Mahasiswa</th><th>Risiko</th><th>Semester</th><th>Faktor utama</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php $res = $row['result']; @endphp
                        <tr>
                            <td>
                                <p class="font-medium">{{ $row['student']->name }}</p>
                                <p class="mono text-sm text-ink-3">{{ $row['student']->username }}</p>
                            </td>
                            <td><x-risk :level="$row['level']" /></td>
                            <td class="num">{{ $res?->semester ?? '-' }}</td>
                            <td class="text-ink-2">{{ $res && count($res->factors) ? $res->factors[0]['label'] : ($res ? 'Tidak ada yang menonjol' : '-') }}</td>
                            <td class="text-right">
                                @if ($res)
                                    <a href="{{ route('dpa.bimbingan.show', $row['student']) }}" class="btn btn-quiet btn-sm">Lihat <x-icon name="arrow-right" size="size-4" /></a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif
@endsection
