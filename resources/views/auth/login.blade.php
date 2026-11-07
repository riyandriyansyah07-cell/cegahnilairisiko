@extends('layouts.base')

@section('title', 'Masuk')

@section('body')
<div class="grid min-h-dvh lg:grid-cols-12">
    <section class="flex flex-col justify-between px-4 py-8 sm:px-10 lg:col-span-5 lg:px-14 lg:py-12">
        <div class="flex items-center justify-between">
            <a href="{{ route('landing') }}" class="font-semibold tracking-tight">Cegah Sebelum Terlambat</a>
            <button type="button" class="btn btn-quiet btn-sm" data-theme-toggle aria-label="Ganti tema terang atau gelap">
                <x-icon name="sun" class="hidden dark:block" /><x-icon name="moon" class="dark:hidden" />
            </button>
        </div>

        <div class="my-14 w-full max-w-sm">
            <h1 class="rise text-4xl">Masuk</h1>
            <p class="rise mt-3 text-ink-2" style="--i: 1">Mahasiswa memakai NIM. DPA dan admin memakai ID pegawai.</p>

            <form id="login-form" method="POST" action="{{ route('login.store') }}" class="rise mt-8 space-y-5" style="--i: 2" novalidate>
                @csrf
                <div>
                    <label for="username" class="label">NIM atau ID pegawai</label>
                    <input id="username" name="username" type="text" inputmode="text" autocomplete="username" required autofocus
                           value="{{ old('username') }}" class="input mono" aria-describedby="username-hint @error('username') username-error @enderror">
                    <p id="username-hint" class="hint mt-1.5">Contoh: 2303045009</p>
                    @error('username')<p id="username-error" class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="label">Kata sandi</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required class="input">
                    @error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn-primary h-11 w-full">Masuk</button>
            </form>
            <p class="mt-6 text-sm text-ink-3">Sesi berakhir otomatis setelah 30 menit tanpa aktivitas. Lupa kata sandi? Hubungi sekretariat FTII.</p>
        </div>

        <p class="text-sm text-ink-3">Capstone Project Kelompok 12, FTII UHAMKA</p>
    </section>

    <aside class="border-t border-line bg-surface px-4 py-10 sm:px-10 lg:col-span-7 lg:border-l lg:border-t-0 lg:px-16 lg:py-12">
        <div class="mx-auto flex h-full max-w-xl flex-col justify-center">
            @if ($demo->isNotEmpty())
                <h2 class="text-2xl">Akun contoh</h2>
                <p class="mt-2 text-ink-2">Tampil hanya di lingkungan lokal. Semua data sintetis. Kata sandi: <span class="mono">password123</span></p>
                <ul class="mt-6 divide-y divide-line border-y border-line">
                    @foreach ($demo as $d)
                        <li class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ $d->name }}</p>
                                <p class="text-sm text-ink-2">{{ $d->roleLabel() }}<span class="mono ml-2 text-ink-3">{{ $d->username }}</span></p>
                            </div>
                            <button type="button" class="btn btn-quiet btn-sm" data-fill="{{ $d->username }}" data-password="password123">Isi</button>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-5 text-sm text-ink-3">Akun mahasiswa pertama belum punya data, jadi alur impor bisa dicoba dari awal.</p>
            @else
                <h2 class="text-3xl">Kendali tetap di tanganmu.</h2>
                <p class="mt-4 max-w-[48ch] text-ink-2">Hasil risikomu hanya terlihat olehmu. DPA baru bisa melihatnya setelah kamu mengizinkan, dan kamu bisa mencabut izin itu kapan saja.</p>
            @endif
        </div>
    </aside>
</div>
@endsection
