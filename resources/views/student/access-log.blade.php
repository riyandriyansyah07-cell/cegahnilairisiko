@extends('layouts.app')

@section('title', 'Catatan akses')

@section('content')
<h1 class="rise text-3xl sm:text-4xl">Catatan akses</h1>
<p class="rise mt-2 max-w-[60ch] text-ink-2" style="--i: 1">Siapa saja yang membuka hasil risikomu, kapan, dan lewat halaman apa. Pembukaan berulang dalam 5 menit dicatat sekali.</p>

@if ($logs->isEmpty())
    <p class="mt-10 max-w-xl text-ink-2">Belum ada catatan. Catatan pertama muncul saat kamu membuka Ringkasan risiko.</p>
@else
    <div class="mt-10 overflow-x-auto">
        <table class="tbl min-w-[36rem]">
            <thead><tr><th>Waktu</th><th>Pengakses</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td class="num whitespace-nowrap">{{ $log->created_at->translatedFormat('j F Y, H:i') }}</td>
                        <td>
                            @if ($log->actor_id === auth()->id())
                                Kamu
                            @else
                                {{ $log->actor?->name ?? 'Akun dihapus' }}
                                <span class="text-sm text-ink-3">({{ $log->actor_role === 'dpa' ? 'DPA' : $log->actor_role }})</span>
                            @endif
                        </td>
                        <td>{{ $log->actionLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @include('partials.pager', ['paginator' => $logs])
@endif
@endsection
