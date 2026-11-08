<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#1c2530;">
<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:28px;">
    <p style="margin:0 0 4px;font-size:13px;color:#5b6773;">{{ config('app.name') }}</p>

    @if ($kind === 'konfirmasi')
        <h1 style="margin:0 0 16px;font-size:20px;">Pengingat lewat email sudah aktif</h1>
        <p style="margin:0 0 12px;line-height:1.6;">Halo {{ $name }}, alamat email ini sudah terdaftar untuk menerima pengingat akademik. Email berikutnya hanya dikirim saat risiko semester terbarumu sedang atau tinggi.</p>
    @else
        <h1 style="margin:0 0 16px;font-size:20px;">Pengingat akademik</h1>
        <p style="margin:0 0 12px;line-height:1.6;">Halo {{ $name }},</p>
    @endif

    @if ($showResult)
        <p style="margin:0 0 12px;line-height:1.6;">
            Hasil semester {{ $result->semester }} menunjukkan risiko <strong>{{ $result->level }}</strong>.
            Buka ringkasan risiko untuk melihat penyebab dan saran langkahnya.
        </p>
        @if (count($suggestions))
            <p style="margin:16px 0 6px;font-weight:bold;">Saran langkah</p>
            <ul style="margin:0 0 16px;padding-left:20px;line-height:1.6;">
                @foreach ($suggestions as $s)
                    <li style="margin-bottom:6px;">{{ $s }}</li>
                @endforeach
            </ul>
        @endif
    @elseif ($kind === 'konfirmasi')
        <p style="margin:0 0 12px;line-height:1.6;">Saat ini belum ada hasil dengan risiko sedang atau tinggi, jadi tidak ada yang perlu kamu lakukan.</p>
    @endif

    <p style="margin:20px 0;">
        <a href="{{ $url }}" style="display:inline-block;background:#3fa7c0;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">Buka ringkasan risiko</a>
    </p>

    <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#5b6773;">Hasil ini perkiraan berdasarkan data yang kamu impor, bukan penilaian atas dirimu.</p>
    <p style="margin:0;font-size:13px;line-height:1.6;color:#5b6773;">
        Tidak ingin menerima email ini? Matikan di menu Privasi: <a href="{{ $privacyUrl }}" style="color:#3fa7c0;">{{ $privacyUrl }}</a>.
        Kalau kamu tidak merasa mendaftarkan alamat ini, abaikan saja email ini.
    </p>
</div>
</body>
</html>
