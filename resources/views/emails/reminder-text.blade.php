{{ config('app.name') }}

@if ($kind === 'konfirmasi')
Pengingat lewat email sudah aktif

Halo {{ $name }}, alamat email ini sudah terdaftar untuk menerima pengingat akademik. Email berikutnya hanya dikirim saat risiko semester terbarumu sedang atau tinggi.
@else
Pengingat akademik

Halo {{ $name }},
@endif

@if ($showResult)
Hasil semester {{ $result->semester }} menunjukkan risiko {{ $result->level }}. Buka ringkasan risiko untuk melihat penyebab dan saran langkahnya.
@if (count($suggestions))

Saran langkah:
@foreach ($suggestions as $s)
- {{ $s }}
@endforeach
@endif
@elseif ($kind === 'konfirmasi')
Saat ini belum ada hasil dengan risiko sedang atau tinggi, jadi tidak ada yang perlu kamu lakukan.
@endif

Buka ringkasan risiko: {{ $url }}

Hasil ini perkiraan berdasarkan data yang kamu impor, bukan penilaian atas dirimu.
Tidak ingin menerima email ini? Matikan di menu Privasi: {{ $privacyUrl }}
Kalau kamu tidak merasa mendaftarkan alamat ini, abaikan saja email ini.
