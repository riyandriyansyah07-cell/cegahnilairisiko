@extends('layouts.base')
@section('title', 'Akses ditolak')
@section('body')
<main class="mx-auto flex min-h-dvh max-w-xl flex-col justify-center px-6">
    <p class="mono text-ink-3">403</p>
    <h1 class="mt-2 text-4xl">Akses ditolak</h1>
    <p class="mt-4 text-ink-2">{{ isset($exception) && $exception->getMessage() && 403 == 403 ? $exception->getMessage() : 'Kamu tidak punya hak untuk membuka halaman ini.' }}</p>
    <div class="mt-8"><a href="{{ url('/') }}" class="btn btn-primary">Kembali ke beranda</a></div>
</main>
@endsection
