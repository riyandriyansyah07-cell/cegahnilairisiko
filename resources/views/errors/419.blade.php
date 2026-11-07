@extends('layouts.base')
@section('title', 'Sesi berakhir')
@section('body')
<main class="mx-auto flex min-h-dvh max-w-xl flex-col justify-center px-6">
    <p class="mono text-ink-3">419</p>
    <h1 class="mt-2 text-4xl">Sesi berakhir</h1>
    <p class="mt-4 text-ink-2">{{ isset($exception) && $exception->getMessage() && 419 == 403 ? $exception->getMessage() : 'Halaman terlalu lama terbuka. Muat ulang lalu coba lagi.' }}</p>
    <div class="mt-8"><a href="{{ url('/') }}" class="btn btn-primary">Kembali ke beranda</a></div>
</main>
@endsection
