<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

class PostalTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $key,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();
        if (! $original instanceof Message) {
            throw new TransportException('Postal transport requires a MIME message.');
        }
        $email = MessageConverter::toEmail($original);

        $payload = [
            'to' => array_map(fn (Address $a) => $a->getAddress(), $email->getTo()),
            'cc' => array_map(fn (Address $a) => $a->getAddress(), $email->getCc()),
            'bcc' => array_map(fn (Address $a) => $a->getAddress(), $email->getBcc()),
            'from' => $this->addressLine($email->getFrom()),
            'sender' => $this->addressLine($email->getFrom()),
            'subject' => $email->getSubject(),
            'plain_body' => $email->getTextBody(),
            'html_body' => $email->getHtmlBody(),
        ];

        $response = Http::withHeaders(['X-Server-API-Key' => $this->key])
            ->acceptJson()
            ->post(rtrim($this->endpoint, '/').'/api/v1/send/message', $payload);

        if ($response->failed() || $response->json('status') !== 'success') {
            $detail = $response->json('data.message') ?? $response->body();
            throw new TransportException("Postal send failed: {$detail}");
        }
    }

    /** @param  Address[]  $addresses */
    private function addressLine(array $addresses): ?string
    {
        $first = $addresses[0] ?? null;

        return $first?->getAddress();
    }

    public function __toString(): string
    {
        return 'postal://'.parse_url($this->endpoint, PHP_URL_HOST);
    }
}
