<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Mail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Sends mail via Microsoft Graph application permission Mail.Send.
 *
 * @see https://learn.microsoft.com/en-us/graph/api/user-sendmail
 */
final class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(
        private readonly MicrosoftGraphMailTokenProvider $tokens,
        private readonly bool $saveToSentItems = false,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $sender = $this->resolveSenderAddress($email, $message);

        $payload = [
            'message' => [
                'subject' => (string) ($email->getSubject() ?? ''),
                'body' => $this->buildBody($email),
                'toRecipients' => $this->mapAddresses($email->getTo()),
                'ccRecipients' => $this->mapAddresses($email->getCc()),
                'bccRecipients' => $this->mapAddresses($email->getBcc()),
                'replyTo' => $this->mapAddresses($email->getReplyTo()),
            ],
            'saveToSentItems' => $this->saveToSentItems,
        ];

        $attachments = $this->mapAttachments($email);
        if ($attachments !== []) {
            $payload['message']['attachments'] = $attachments;
        }

        $this->postSendMail($sender, $payload, retryOnUnauthorized: true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSendMail(string $sender, array $payload, bool $retryOnUnauthorized): void
    {
        $token = $this->tokens->getAccessToken(forceRefresh: ! $retryOnUnauthorized);
        $url = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($sender).'/sendMail';

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->withToken($token)
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Microsoft Graph sendMail timed out', [
                'sender' => $sender,
                'message' => $exception->getMessage(),
            ]);

            throw new TransportException(
                'Microsoft Graph sendMail failed: '.$exception->getMessage(),
                0,
                $exception,
            );
        }

        if ($response->status() === 401 && $retryOnUnauthorized) {
            $this->tokens->forgetCachedToken();
            $this->postSendMail($sender, $payload, retryOnUnauthorized: false);

            return;
        }

        // Graph returns 202 Accepted with empty body on success.
        if ($response->successful()) {
            return;
        }

        $detail = (string) ($response->json('error.message') ?? $response->body());
        Log::warning('Microsoft Graph sendMail failed', [
            'sender' => $sender,
            'status' => $response->status(),
            'error' => $response->json('error'),
        ]);

        throw new TransportException(
            sprintf('Microsoft Graph sendMail failed (%d): %s', $response->status(), $detail),
            $response->status(),
        );
    }

    private function resolveSenderAddress(Email $email, SentMessage $message): string
    {
        $from = $email->getFrom()[0] ?? null;
        if ($from instanceof Address && $from->getAddress() !== '') {
            return strtolower($from->getAddress());
        }

        $envelopeSender = $message->getEnvelope()->getSender()->getAddress();
        if ($envelopeSender !== '') {
            return strtolower($envelopeSender);
        }

        $configured = strtolower(trim((string) config('mail.from.address', '')));
        if ($configured !== '') {
            return $configured;
        }

        throw new TransportException(
            'Microsoft Graph sendMail requires a From address (MAIL_FROM_ADDRESS or message from).',
        );
    }

    /**
     * @return array{contentType: string, content: string}
     */
    private function buildBody(Email $email): array
    {
        $html = $email->getHtmlBody();
        if (is_string($html) && $html !== '') {
            return [
                'contentType' => 'HTML',
                'content' => $html,
            ];
        }

        $text = $email->getTextBody();

        return [
            'contentType' => 'Text',
            'content' => is_string($text) ? $text : '',
        ];
    }

    /**
     * @param  list<Address>  $addresses
     * @return list<array{emailAddress: array{address: string, name?: string}}>
     */
    private function mapAddresses(array $addresses): array
    {
        $out = [];
        foreach ($addresses as $address) {
            if (! $address instanceof Address) {
                continue;
            }

            $item = [
                'emailAddress' => [
                    'address' => $address->getAddress(),
                ],
            ];
            $name = trim($address->getName());
            if ($name !== '') {
                $item['emailAddress']['name'] = $name;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapAttachments(Email $email): array
    {
        $out = [];

        foreach ($email->getAttachments() as $part) {
            if (! $part instanceof DataPart) {
                continue;
            }

            $headers = $part->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename')
                ?? $headers->getHeaderParameter('Content-Type', 'name')
                ?? 'attachment';
            $contentTypeHeader = $headers->get('Content-Type');
            $contentType = $contentTypeHeader !== null
                ? (string) $contentTypeHeader->getBody()
                : 'application/octet-stream';

            $item = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $filename,
                'contentType' => $contentType,
                'contentBytes' => base64_encode($part->getBody()),
            ];

            if ($part->getContentId() !== null && $part->getContentId() !== '') {
                $item['contentId'] = $part->getContentId();
                $item['isInline'] = true;
            }

            $out[] = $item;
        }

        return $out;
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}
