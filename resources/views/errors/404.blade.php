@extends('layouts.base')
@section('title', 'Halaman tidak ditemukan')
@section('body')
<main class="mx-auto flex min-h-dvh max-w-xl flex-col justify-center px-6">
    <p class="mono text-ink-3">404</p>
    <h1 class="mt-2 text-4xl">Halaman tidak ditemukan</h1>
    <p class="mt-4 text-ink-2">{{ isset($exception) && $exception->getMessage() && 404 == 403 ? $exception->getMessage() : 'Alamatnya mungkin salah atau halamannya sudah dipindah.' }}</p>
    <div class="mt-8"><a href="{{ url('/') }}" class="btn btn-primary">Kembali ke beranda</a></div>
</main>
@endsection
