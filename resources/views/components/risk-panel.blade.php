{{-- Panel hasil risiko. Dipakai di ringkasan mahasiswa, detail DPA, dan pratinjau beranda. --}}
@props(['result', 'thresholds' => ['sedang' => 2.5, 'tinggi' => 5.0], 'audience' => 'student', 'version' => null, 'compact' => false])
@php
    $level = $result->level;
    $score = (float) $result->score;
    $factors = $result->factors ?? [];
    $explanationUnavailable = ! empty($result->features['explanation_unavailable']);
    if ($compact) {
        $factors = array_slice($factors, 0, 3);
    }
    $suggestions = collect($result->suggestions ?? []);
    if ($audience === 'dpa') {
        $suggestions = $suggestions->reject(fn ($s) => str_contains($s, 'DPA'));
    }
    $copy = [
        'rendah' => 'Pola kehadiran dan nilai semester ini berada di jalur yang aman. Tidak ada faktor yang menonjol.',
        'sedang' => 'Ada beberapa pola yang layak diperhatikan lebih awal. Waktunya masih cukup untuk memperbaiki.',
        'tinggi' => 'Pola data mirip mahasiswa yang biasanya butuh pendampingan lebih awal. Ini perkiraan, dan masih ada waktu untuk bertindak.',
    ];
    $pos = fn (float $v) => max(0, min(100, $v / \App\Support\RiskModel::MAX_SCORE * 100));
    $maxPoints = 3.0;
@endphp

<article class="risk-{{ $level }}">
    <div class="border-l-4 pl-5 sm:pl-7" style="border-color: var(--c)">
        <p class="text-sm text-ink-2">Semester {{ $result->semester }}</p>
        <div class="mt-1 flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
            <h2 class="risk text-4xl sm:text-5xl">{{ ['rendah' => 'Risiko rendah', 'sedang' => 'Risiko sedang', 'tinggi' => 'Risiko tinggi'][$level] }}</h2>
            <p class="num text-ink">
                <span class="mono text-3xl font-medium">{{ \App\Support\Fmt::num($score, 1) }}</span><span class="text-ink-3"> dari 10</span>
            </p>
        </div>
        <p class="mt-4 max-w-[60ch] text-ink-2">{{ $copy[$level] }}</p>

        <div class="mt-7" role="img" aria-label="Skor risiko {{ \App\Support\Fmt::num($score, 1) }} dari 10. Batas sedang {{ \App\Support\Fmt::num($thresholds['sedang'], 2) }}, batas tinggi {{ \App\Support\Fmt::num($thresholds['tinggi'], 2) }}.">
            <div class="relative pb-6">
                <div class="relative h-3 overflow-hidden rounded-md">
                    <div class="absolute inset-y-0 left-0" style="width: {{ $pos($thresholds['sedang']) }}%; background: color-mix(in oklab, var(--ok) 32%, transparent)"></div>
                    <div class="absolute inset-y-0" style="left: {{ $pos($thresholds['sedang']) }}%; width: {{ $pos($thresholds['tinggi']) - $pos($thresholds['sedang']) }}%; background: color-mix(in oklab, var(--warn) 32%, transparent)"></div>
                    <div class="absolute inset-y-0 right-0" style="left: {{ $pos($thresholds['tinggi']) }}%; background: color-mix(in oklab, var(--bad) 32%, transparent)"></div>
                </div>
                <span class="absolute -top-1 h-5 w-0.5 -translate-x-1/2 bg-ink" style="left: {{ $pos($score) }}%"></span>
                <div class="num absolute inset-x-0 bottom-0 h-4 text-xs text-ink-3">
                    <span class="absolute left-0">0</span>
                    <span class="absolute -translate-x-1/2" style="left: {{ $pos($thresholds['sedang']) }}%">{{ \App\Support\Fmt::num($thresholds['sedang'], 1) }}</span>
                    <span class="absolute -translate-x-1/2" style="left: {{ $pos($thresholds['tinggi']) }}%">{{ \App\Support\Fmt::num($thresholds['tinggi'], 1) }}</span>
                    <span class="absolute right-0">10</span>
                </div>
            </div>
        </div>
    </div>

    <section class="mt-10" aria-labelledby="faktor-{{ $audience }}">
        <h3 id="faktor-{{ $audience }}" class="text-xl">{{ $audience === 'dpa' ? 'Faktor utama' : 'Penyebab utama' }}</h3>
        @if ($explanationUnavailable)
            <p class="mt-3 text-ink-2">Penjelasan faktor dari model ML tidak tersedia untuk hasil ini. Tekan Hitung ulang untuk mencoba lagi.</p>
        @elseif (count($factors))
            <ul class="mt-4 space-y-5">
                @foreach ($factors as $f)
                    <li class="rise" style="--i: {{ $loop->index }}">
                        <div class="flex items-baseline justify-between gap-4">
                            <p class="font-medium">{{ $f['label'] }}</p>
                            <p class="num mono shrink-0 text-ink-2">+{{ \App\Support\Fmt::num($f['points'], 2) }} poin</p>
                        </div>
                        <p class="mt-0.5 text-sm text-ink-2">{{ $f['detail'] }}</p>
                        <div class="mt-2 h-1.5 w-full" aria-hidden="true">
                            <div class="grow h-full rounded-md bg-accent" style="--i: {{ $loop->index }}; width: {{ min(100, $f['points'] / $maxPoints * 100) }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
            @unless ($compact)<p class="mt-4 text-sm text-ink-3">Panjang batang dibandingkan dengan sumbangan poin terbesar yang mungkin untuk satu faktor (3 poin).</p>@endunless
        @else
            <p class="mt-3 text-ink-2">Tidak ada faktor yang menambah risiko pada semester ini.</p>
        @endif
    </section>

    @unless ($compact)
    <section class="mt-10" aria-labelledby="saran-{{ $audience }}">
        <h3 id="saran-{{ $audience }}" class="text-xl">{{ $audience === 'dpa' ? 'Bahan untuk percakapan pendampingan' : 'Langkah yang bisa dicoba' }}</h3>
        <ol class="mt-4 space-y-3">
            @foreach ($suggestions as $s)
                <li class="flex gap-3">
                    <span class="mono mt-0.5 w-5 shrink-0 text-ink-3">{{ $loop->iteration }}</span>
                    <span class="max-w-[62ch]">{{ $s }}</span>
                </li>
            @endforeach
        </ol>
    </section>

    @endunless

    <p class="mt-8 max-w-[62ch] border-t border-line pt-4 text-sm text-ink-3">
        Hasil ini perkiraan dari pola data, bukan penilaian atas pribadi mahasiswa dan bukan keputusan. Data per {{ $result->data_as_of?->translatedFormat('j F Y') }}@if ($version), model {{ $version }}@endif.
    </p>
</article>
