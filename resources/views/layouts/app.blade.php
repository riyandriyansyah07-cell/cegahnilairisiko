@extends('layouts.base')

@php
    $user = auth()->user();
    $nav = match ($user->role) {
        'mahasiswa' => [
            ['mhs.ringkasan', 'Ringkasan risiko', 'chart-line-up'],
            ['mhs.impor', 'Impor data', 'upload-simple'],
            ['mhs.riwayat', 'Riwayat semester', 'clock-counter-clockwise'],
            ['mhs.privasi', 'Privasi dan izin', 'shield-check'],
            ['mhs.catatan', 'Catatan akses', 'eye'],
        ],
        'dpa' => [
            ['dpa.bimbingan', 'Mahasiswa bimbingan', 'users'],
        ],
        default => [
            ['admin.akun', 'Akun dan peran', 'identification-card'],
            ['admin.historis', 'Data historis', 'database'],
            ['admin.model', 'Model prediksi', 'brain'],
            ['admin.catatan', 'Catatan akses', 'eye'],
        ],
    };
@endphp

@section('body')
    <div class="no-print sticky top-0 z-30 flex h-14 items-center justify-between border-b border-line bg-surface px-4 lg:hidden">
        <a href="{{ route('beranda') }}" class="font-semibold tracking-tight">Cegah Sebelum Terlambat</a>
        <button type="button" class="btn btn-quiet btn-sm" data-nav-toggle aria-expanded="false" aria-controls="sidebar">
            <x-icon name="list" /> Menu
        </button>
    </div>

    <aside id="sidebar" class="sidebar no-print fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-line bg-bg px-4 py-6 lg:z-20">
        <a href="{{ route('beranda') }}" class="px-3">
            <span class="block text-lg font-semibold leading-tight tracking-tight">Cegah Sebelum<br>Terlambat</span>
            <span class="mt-1 block text-xs text-ink-3">Peringatan dini akademik, FTII UHAMKA</span>
        </a>

        <nav class="mt-8 flex flex-col gap-1" aria-label="Menu utama">
            @foreach ($nav as [$route, $label, $icon])
                <a href="{{ route($route) }}" class="nav-link" @if (request()->routeIs($route.'*')) aria-current="page" @endif>
                    <x-icon :name="$icon" /> {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="mt-auto border-t border-line pt-4">
            <div class="px-3">
                <p class="truncate font-medium">{{ $user->name }}</p>
                <p class="truncate text-xs text-ink-3">{{ $user->roleLabel() }}</p>
            </div>
            <div class="mt-3 flex items-center gap-2 px-1">
                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                    @csrf
                    <button class="nav-link w-full" type="submit"><x-icon name="sign-out" /> Keluar</button>
                </form>
                <button type="button" class="btn btn-quiet btn-sm" data-theme-toggle aria-label="Ganti tema terang atau gelap">
                    <x-icon name="sun" class="hidden dark:block" /><x-icon name="moon" class="dark:hidden" />
                </button>
            </div>
        </div>
    </aside>

    <main class="lg:pl-64">
        <div class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-8 lg:py-12">
            @include('partials.flash')
            @yield('content')
        </div>
    </main>
@endsection
