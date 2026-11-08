<?php

namespace App\Mail;

use App\Models\RiskResult;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email pengingat untuk mahasiswa. Dikirim ke alamat yang diisi mahasiswa di menu Privasi.
 *
 * kind = 'risiko'     : pengingat saat risiko semester terbaru sedang atau tinggi.
 * kind = 'konfirmasi' : konfirmasi sekali kirim saat mahasiswa mengaktifkan atau mengganti alamat email.
 */
class ReminderMail extends Mailable
{
    public const KIND_RISK = 'risiko';

    public const KIND_CONFIRM = 'konfirmasi';

    public function __construct(
        public User $user,
        public string $kind = self::KIND_RISK,
        public ?RiskResult $result = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->kind === self::KIND_CONFIRM
                ? 'Pengingat lewat email sudah aktif'
                : 'Pengingat akademik dari '.config('app.name'),
        );
    }

    public function content(): Content
    {
        $showResult = $this->result !== null && $this->result->level !== 'rendah';

        return new Content(
            view: 'emails.reminder',
            text: 'emails.reminder-text',
            with: [
                'name' => $this->user->name,
                'kind' => $this->kind,
                'result' => $this->result,
                'showResult' => $showResult,
                'suggestions' => $showResult ? array_slice($this->result->suggestions ?? [], 0, 3) : [],
                'url' => route('mhs.ringkasan'),
                'privacyUrl' => route('mhs.privasi'),
            ],
        );
    }
}
