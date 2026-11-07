@extends('layouts.app')

@section('title', 'Akun dan peran')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="rise text-3xl sm:text-4xl">Akun dan peran</h1>
        <p class="rise mt-2 text-ink-2 num" style="--i: 1">{{ $totals['mahasiswa'] ?? 0 }} mahasiswa, {{ $totals['dpa'] ?? 0 }} DPA, {{ $totals['admin'] ?? 0 }} admin.</p>
    </div>
    <a href="{{ route('admin.akun.baru') }}" class="btn btn-primary"><x-icon name="plus" /> Tambah akun</a>
</div>

<form method="GET" class="mt-8 flex flex-wrap items-end gap-3">
    <div class="w-full max-w-xs">
        <label for="q" class="label">Cari nama atau NIM</label>
        <input id="q" name="q" value="{{ $q }}" class="input" type="search">
    </div>
    <div>
        <label for="peran" class="label">Peran</label>
        <select id="peran" name="peran" class="input">
            <option value="">Semua</option>
            @foreach (['mahasiswa' => 'Mahasiswa', 'dpa' => 'DPA', 'admin' => 'Admin'] as $v => $l)
                <option value="{{ $v }}" @selected($role === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-quiet" type="submit"><x-icon name="magnifying-glass" /> Cari</button>
</form>

<div class="mt-8 overflow-x-auto">
    <table class="tbl min-w-[46rem]">
        <thead><tr><th>Nama</th><th>NIM atau ID</th><th>Peran</th><th>DPA</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead>
        <tbody>
            @forelse ($users as $u)
                <tr>
                    <td class="font-medium">{{ $u->name }}</td>
                    <td class="mono">{{ $u->username }}</td>
                    <td>{{ $u->roleLabel() }}</td>
                    <td class="text-ink-2">{{ $u->dpa?->name ?? '-' }}</td>
                    <td>{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                    <td class="text-right"><a href="{{ route('admin.akun.ubah', $u) }}" class="btn btn-quiet btn-sm"><x-icon name="pencil-simple" /> Ubah</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-ink-2">Tidak ada akun yang cocok.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@include('partials.pager', ['paginator' => $users])
@endsection
