@extends('layouts.base')
@section('title', 'Terjadi kesalahan')
@section('body')
<main class="mx-auto flex min-h-dvh max-w-xl flex-col justify-center px-6">
    <p class="mono text-ink-3">500</p>
    <h1 class="mt-2 text-4xl">Terjadi kesalahan</h1>
    <p class="mt-4 text-ink-2">{{ isset($exception) && $exception->getMessage() && 500 == 403 ? $exception->getMessage() : 'Ada masalah di server. Coba lagi sebentar lagi.' }}</p>
    <div class="mt-8"><a href="{{ url('/') }}" class="btn btn-primary">Kembali ke beranda</a></div>
</main>
@endsection
