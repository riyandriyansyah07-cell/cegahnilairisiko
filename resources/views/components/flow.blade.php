{{-- Alur mahasiswa: impor data, lihat risiko, atur privasi. $done berisi nama langkah yang sudah selesai. --}}
@props(['done' => [], 'current' => null])
@php
    $steps = [
        'impor' => ['Impor data', 'Unggah KRS, nilai, absensi', 'mhs.impor'],
        'risiko' => ['Lihat risiko', 'Baca penyebab dan saran', 'mhs.ringkasan'],
        'privasi' => ['Atur izin DPA', 'Pilih siapa yang boleh melihat', 'mhs.privasi'],
    ];
@endphp
<ol class="no-print grid gap-3 sm:grid-cols-3" aria-label="Alur penggunaan">
    @foreach ($steps as $key => [$title, $desc, $route])
        @php $isDone = in_array($key, $done, true); $isCurrent = $current === $key; @endphp
        <li>
            <a href="{{ route($route) }}" @if ($isCurrent) aria-current="step" @endif
               class="group flex h-full items-start gap-3 rounded-md border px-4 py-3 transition-colors hover:bg-surface {{ $isCurrent ? 'border-accent bg-surface' : 'border-line' }}">
                <span class="mono mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-md border text-xs {{ $isDone ? 'border-ok bg-ok text-bg' : 'border-line-strong text-ink-2' }}">
                    @if ($isDone) <x-icon name="check" size="size-3.5" /> @else {{ $loop->iteration }} @endif
                </span>
                <span class="min-w-0">
                    <span class="block font-medium">{{ $title }}</span>
                    <span class="block text-sm text-ink-2">{{ $desc }}</span>
                </span>
            </a>
        </li>
    @endforeach
</ol>
