@extends('layouts.app')

@section('title', 'Privasi dan izin')

@section('content')
<h1 class="rise text-3xl sm:text-4xl">Privasi dan izin</h1>
<p class="rise mt-2 max-w-[60ch] text-ink-2" style="--i: 1">Kamu yang memutuskan siapa yang melihat hasil risikomu. Setiap akses tercatat dan bisa kamu baca di Catatan akses.</p>

<div class="mt-8"><x-flow :current="'privasi'" :done="$consent ? ['privasi'] : []" /></div>

<div class="mt-12 grid gap-14 lg:grid-cols-12">
    <section class="lg:col-span-7" aria-labelledby="izin-dpa">
        <h2 id="izin-dpa" class="text-2xl">Izin untuk DPA</h2>
        @if (! $dpa)
            <p class="mt-4 text-ink-2">Belum ada DPA yang ditetapkan untukmu. Hubungi sekretariat FTII agar DPA-mu didaftarkan.</p>
        @else
            <p class="mt-4 text-lg">{{ $dpa->name }}</p>
            <p class="mt-2 max-w-[56ch] text-ink-2">
                @if ($consent?->granted)
                    DPA boleh melihat tingkat risiko dan faktor utamamu sejak {{ $consent->changed_at->translatedFormat('j F Y, H:i') }}.
                @elseif ($consent)
                    Kamu mencabut izin pada {{ $consent->changed_at->translatedFormat('j F Y, H:i') }}. DPA tidak bisa melihat apa pun tentang hasilmu.
                @else
                    Kamu belum memberi izin. DPA tidak bisa melihat apa pun tentang hasilmu.
                @endif
            </p>
            <form method="POST" action="{{ route('mhs.privasi.izin') }}" class="mt-5">
                @csrf
                @if ($consent?->granted)
                    <input type="hidden" name="granted" value="0">
                    <button type="submit" class="btn btn-danger"><x-icon name="eye-slash" /> Cabut izin</button>
                @else
                    <input type="hidden" name="granted" value="1">
                    <button type="submit" class="btn btn-primary"><x-icon name="eye" /> Izinkan DPA melihat</button>
                @endif
            </form>

            <dl class="mt-8 grid gap-6 sm:grid-cols-2">
                <div>
                    <dt class="font-medium">Yang bisa dilihat DPA</dt>
                    <dd class="mt-1 text-sm text-ink-2">Nama dan NIM, tingkat risiko, skor, dan faktor utama per semester.</dd>
                </div>
                <div>
                    <dt class="font-medium">Yang tidak pernah terlihat</dt>
                    <dd class="mt-1 text-sm text-ink-2">Nilai per mata kuliah, absensi per mata kuliah, dan berkas yang kamu unggah.</dd>
                </div>
            </dl>
        @endif
    </section>

    <section class="border-t border-line pt-10 lg:col-span-5 lg:border-l lg:border-t-0 lg:pl-10 lg:pt-0" aria-labelledby="pengingat">
        <h2 id="pengingat" class="text-2xl">Pengingat</h2>
        <p class="mt-2 text-sm text-ink-2">Dikirim saat risiko semester terbaru sedang atau tinggi.</p>
        <form method="POST" action="{{ route('mhs.privasi.pengingat') }}" class="mt-5 space-y-5">
            @csrf
            <label class="flex items-start gap-3">
                <input type="checkbox" name="remind_in_app" value="1" class="mt-1 size-4 accent-[var(--accent)]" @checked(old('remind_in_app', $settings->remind_in_app))>
                <span>Tampilkan di aplikasi<span class="block text-sm text-ink-2">Muncul di Ringkasan risiko.</span></span>
            </label>
            <label class="flex items-start gap-3">
                <input type="checkbox" name="remind_email" value="1" class="mt-1 size-4 accent-[var(--accent)]" @checked(old('remind_email', $settings->remind_email))>
                <span>Kirim lewat email<span class="block text-sm text-ink-2">Layanan email bersifat opsional. Pengingat di aplikasi tetap jalan tanpanya.</span></span>
            </label>
            <div>
                <label for="email" class="label">Alamat email</label>
                <input id="email" name="email" type="email" autocomplete="email" class="input" value="{{ old('email', $user->email) }}" aria-describedby="@error('email') email-error @enderror">
                @error('email')<p id="email-error" class="field-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Simpan pengingat</button>
        </form>
    </section>
</div>

<section class="mt-16 border-t border-line pt-10" aria-labelledby="data-tersimpan">
    <h2 id="data-tersimpan" class="text-2xl">Yang tersimpan tentang dirimu</h2>
    <ul class="mt-4 grid max-w-3xl gap-3 text-ink-2">
        <li>NIM-mu diubah menjadi kode acak (HMAC-SHA256 dengan garam) sebelum data akademik disimpan. Data akademik tidak memuat NIM asli.</li>
        <li>Berkas yang kamu unggah dibaca, diperiksa, lalu dibuang. Yang disimpan hanya mata kuliah, SKS, nilai, dan kehadiran yang lolos pemeriksaan.</li>
        <li>Admin tidak melihat hasil risikomu. Admin hanya melihat catatan akses dengan kode samar.</li>
    </ul>
</section>
@endsection
