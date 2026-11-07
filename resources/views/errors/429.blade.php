@extends('layouts.base')
@section('title', 'Terlalu banyak permintaan')
@section('body')
<main class="mx-auto flex min-h-dvh max-w-xl flex-col justify-center px-6">
    <p class="mono text-ink-3">429</p>
    <h1 class="mt-2 text-4xl">Terlalu banyak permintaan</h1>
    <p class="mt-4 text-ink-2">{{ isset($exception) && $exception->getMessage() && 429 == 403 ? $exception->getMessage() : 'Tunggu sebentar lalu coba lagi.' }}</p>
    <div class="mt-8"><a href="{{ url('/') }}" class="btn btn-primary">Kembali ke beranda</a></div>
</main>
@endsection
