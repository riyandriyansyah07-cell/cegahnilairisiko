@extends('layouts.base')

@section('title', 'Tahu risikomu lebih awal')

@section('body')
<header class="sticky top-0 z-30 border-b border-line bg-bg/90 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-8">
        <a href="{{ route('landing') }}" class="font-semibold tracking-tight">Cegah Sebelum Terlambat</a>
        <nav class="flex items-center gap-1 sm:gap-2" aria-label="Navigasi utama">
            <a href="#alur" class="nav-link hidden sm:flex">Alur</a>
            <a href="#privasi" class="nav-link hidden sm:flex">Privasi</a>
            <button type="button" class="btn btn-quiet btn-sm" data-theme-toggle aria-label="Ganti tema terang atau gelap">
                <x-icon name="sun" class="hidden dark:block" /><x-icon name="moon" class="dark:hidden" />
            </button>
            @auth
                <a href="{{ route('beranda') }}" class="btn btn-primary btn-sm">Buka beranda</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Masuk</a>
            @endauth
        </nav>
    </div>
</header>

<main>
    {{-- Hero --}}
    <section class="mx-auto grid max-w-7xl gap-12 px-4 pb-20 pt-12 sm:px-8 lg:min-h-[calc(100dvh-4rem)] lg:grid-cols-12 lg:items-center lg:gap-10 lg:pt-8">
        <div class="lg:col-span-5">
            <h1 class="rise text-5xl sm:text-6xl">Tahu risikomu lebih awal.</h1>
            <p class="rise mt-6 max-w-[44ch] text-lg text-ink-2" style="--i: 1">Impor KRS, nilai, dan absensi. Sistem menghitung risiko akademikmu dan menjelaskan penyebabnya sebelum nilai akhir terbit.</p>
            <div class="rise mt-9 flex flex-wrap gap-3" style="--i: 2">
                @auth
                    <a href="{{ route('beranda') }}" class="btn btn-primary h-12 px-6">Buka beranda</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary h-12 px-6">Masuk</a>
                @endauth
                <a href="#alur" class="btn btn-quiet h-12 px-6">Lihat alurnya</a>
            </div>
        </div>

        <div class="rise lg:col-span-7" style="--i: 3">
            <div class="rounded-md border border-line-strong bg-surface p-6 sm:p-9 lg:ml-8">
                <x-risk-panel :result="$sample" version="aturan-v1" compact />
            </div>
            <p class="mt-3 text-sm text-ink-3 lg:ml-8">Contoh tampilan hasil dengan data sintetis. Komponen yang sama muncul di akunmu setelah impor.</p>
        </div>
    </section>

    {{-- Masalah --}}
    <section class="border-y border-line bg-surface">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-8 lg:py-28">
            <h2 class="max-w-[20ch] text-4xl sm:text-5xl">Nilai akhir datang saat semua sudah terlambat.</h2>
            <div class="mt-14 grid gap-x-16 gap-y-12 sm:grid-cols-2">
                <div>
                    <p class="num text-7xl font-semibold tracking-tighter sm:text-8xl">11<span class="ml-2 text-3xl font-normal tracking-normal text-ink-3 sm:text-4xl">dari 16</span></p>
                    <p class="mt-3 max-w-[34ch] text-ink-2">mahasiswa baru mengetahui capaian akademiknya setelah nilai akhir semester terbit.</p>
                </div>
                <div class="sm:mt-20">
                    <p class="num text-7xl font-semibold tracking-tighter sm:text-8xl">2<span class="ml-2 text-3xl font-normal tracking-normal text-ink-3 sm:text-4xl">dari 16</span></p>
                    <p class="mt-3 max-w-[34ch] text-ink-2">mahasiswa sudah tahu sebelum UTS. Penilaian risiko terpadu belum tersedia di portal.</p>
                </div>
                <div>
                    <p class="num text-7xl font-semibold tracking-tighter sm:text-8xl">13<span class="ml-2 text-3xl font-normal tracking-normal text-ink-3 sm:text-4xl">dari 16</span></p>
                    <p class="mt-3 max-w-[34ch] text-ink-2">menilai kebutuhan cek risiko mandiri tinggi (skor 4 atau 5).</p>
                </div>
                <div class="sm:mt-20">
                    <p class="num text-7xl font-semibold tracking-tighter sm:text-8xl">13<span class="ml-2 text-3xl font-normal tracking-normal text-ink-3 sm:text-4xl">dari 16</span></p>
                    <p class="mt-3 max-w-[34ch] text-ink-2">khawatir pada keamanan dan privasi data akademiknya. Sistem ini dirancang dengan kekhawatiran itu di depan.</p>
                </div>
            </div>
            <p class="mt-14 max-w-[60ch] text-sm text-ink-3">Survei awal terhadap 16 mahasiswa FTII UHAMKA (14 dari angkatan 2023 semester 7). Hasilnya indikatif. Kebutuhan DPA dan sekretariat masih asumsi dan perlu divalidasi.</p>
        </div>
    </section>

    {{-- Alur --}}
    <section id="alur" class="mx-auto max-w-7xl scroll-mt-16 px-4 py-20 sm:px-8 lg:py-28">
        <h2 class="max-w-[22ch] text-4xl sm:text-5xl">Dari berkas portal sampai saran langkah.</h2>
        <ol class="relative mt-14 grid gap-10 lg:grid-cols-4 lg:gap-8">
            <span class="absolute left-[1.15rem] top-3 block h-[calc(100%-1.5rem)] w-px bg-line-strong lg:left-0 lg:top-[1.15rem] lg:h-px lg:w-full" aria-hidden="true"></span>
            @foreach ([
                ['Impor', 'Mahasiswa', 'Unduh berkas KRS, nilai, dan absensi dari portal akademik, lalu unggah. Sistem memeriksa format dan membuang berkas mentahnya.'],
                ['Hitung', 'Sistem', 'NIM disamarkan, lalu risiko rendah, sedang, atau tinggi dihitung bersama faktor penyebabnya dan tanggal data serta versi model.'],
                ['Setujui', 'Mahasiswa', 'Kamu memilih apakah DPA boleh melihat hasilnya. Izin bisa dicabut kapan saja dan berlaku seketika.'],
                ['Dampingi', 'DPA', 'DPA melihat daftar bimbingan yang sudah menyetujui, diurutkan menurut risiko, lalu mengajak bicara lebih awal.'],
            ] as $i => [$verb, $who, $text])
                <li class="relative pl-14 lg:pl-0 lg:pt-14">
                    <span class="mono absolute left-0 top-0 flex size-9 items-center justify-center rounded-md border border-line-strong bg-bg text-sm">{{ $i + 1 }}</span>
                    <h3 class="text-2xl">{{ $verb }}</h3>
                    <p class="mt-1 text-sm text-accent">{{ $who }}</p>
                    <p class="mt-3 max-w-[36ch] text-ink-2">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Privasi --}}
    <section id="privasi" class="scroll-mt-16 border-t border-line bg-surface">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-8 lg:py-28">
            <h2 class="max-w-[24ch] text-4xl sm:text-5xl">Siapa yang bisa melihat apa.</h2>
            <p class="mt-5 max-w-[56ch] text-ink-2">Hak akses diperiksa di server pada setiap permintaan. Setiap kali hasil risiko dibuka, ada catatannya yang bisa kamu baca.</p>

            <div class="mt-12 overflow-x-auto">
                <table class="tbl min-w-[40rem]">
                    <caption class="sr-only">Hak akses data menurut peran</caption>
                    <thead>
                        <tr><th scope="col">Data</th><th scope="col">Mahasiswa</th><th scope="col">DPA</th><th scope="col">Admin</th></tr>
                    </thead>
                    <tbody>
                        @foreach ([
                            ['Berkas impor mentah', 'Mengunggah, lalu terhapus', 'Tidak', 'Tidak'],
                            ['Data akademik tersamar', 'Miliknya sendiri', 'Tidak', 'Tidak'],
                            ['Tingkat risiko dan faktor utama', 'Miliknya sendiri', 'Hanya yang menyetujui', 'Tidak'],
                            ['Catatan akses', 'Miliknya sendiri', 'Tidak', 'Semua, dengan kode samar'],
                            ['Data historis anonim dan model', 'Tidak', 'Tidak', 'Ya'],
                        ] as $row)
                            <tr>
                                <th scope="row" class="!border-b !border-line !py-4 !text-base !font-medium !text-ink">{{ $row[0] }}</th>
                                @foreach (array_slice($row, 1) as $cell)
                                    <td class="py-4 {{ $cell === 'Tidak' ? 'text-ink-3' : '' }}">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- Penutup --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-8 lg:py-28">
        <div class="grid gap-8 lg:grid-cols-12 lg:items-end">
            <h2 class="text-4xl sm:text-5xl lg:col-span-8">Satu berkas hari ini, satu percakapan lebih awal besok.</h2>
            <div class="lg:col-span-4 lg:text-right">
                @auth
                    <a href="{{ route('beranda') }}" class="btn btn-primary h-12 px-6">Buka beranda</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary h-12 px-6">Masuk</a>
                @endauth
            </div>
        </div>
    </section>
</main>

<footer class="border-t border-line">
    <div class="mx-auto max-w-7xl px-4 py-10 text-sm text-ink-2 sm:px-8">
        <p class="font-medium text-ink">Capstone Project Kelompok 12</p>
        <p class="mt-1">Program Studi Sistem dan Teknologi Informasi, FTII Universitas Muhammadiyah Prof. Dr. Hamka.</p>
        <p class="mt-1">Muhammad Rafa Kuswanto, Yobi Fazri Rinandi, Riyan Adriyansyah. Dosen: Dr. Ir. Mohammad Givi Efgivia, M.Kom.</p>
    </div>
</footer>
@endsection
