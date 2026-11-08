<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

/**
 * Mengirim email lewat web app Google Apps Script milik akun Gmail (HTTPS, gratis).
 * Dipakai di hosting yang memblokir SMTP (mis. Railway Free/Hobby).
 */
class AppsScriptTransport extends AbstractTransport
{
    public function __construct(private string $url, private string $secret)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $recipients = array_map(fn ($a) => $a->getAddress(), $message->getEnvelope()->getRecipients());

        $response = Http::timeout(20)->asJson()->post($this->url, [
            'secret' => $this->secret,
            'to' => implode(',', $recipients),
            'subject' => (string) $email->getSubject(),
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
            'name' => config('mail.from.name'),
        ]);

        if (! $response->successful() || $response->json('ok') !== true) {
            throw new RuntimeException('Apps Script menolak pengiriman: '.($response->json('error') ?? 'HTTP '.$response->status()));
        }
    }

    public function __toString(): string
    {
        return 'appsscript';
    }
}