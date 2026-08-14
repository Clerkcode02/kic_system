<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * CLAUDE.md stack table — production/staging mail goes through the Gmail
 * API (OAuth2), never SMTP, never a third-party ESP. Registered as the
 * "gmail" mailer via Mail::extend() in MailServiceProvider so every
 * existing Mail::/Notification:: call is unchanged; only config/mail.php's
 * default mailer differs between local (Mailpit/smtp) and
 * staging/production (gmail).
 *
 * Sends the message's raw RFC 2822 MIME as base64url through
 * users.messages.send — the same shape SMTP would have produced, so
 * nothing downstream (headers, multipart, attachments) needs to know the
 * transport changed.
 */
class GmailApiTransport extends AbstractTransport
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SEND_URL = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'gmail+api://oauth2';
    }

    protected function doSend(SentMessage $message): void
    {
        $raw = base64_encode($message->toString());
        $raw = str_replace(['+', '/', '='], ['-', '_', ''], $raw);

        $response = Http::withToken($this->accessToken())
            ->post(self::SEND_URL, ['raw' => $raw]);

        if ($response->failed()) {
            throw new TransportException(
                'Gmail API send failed: '.$response->status().' '.$response->body(),
            );
        }
    }

    private function accessToken(): string
    {
        return Cache::remember('gmail_api_access_token', 3000, function (): string {
            $response = Http::asForm()->post(self::TOKEN_URL, [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->failed()) {
                throw new RuntimeException(
                    'Failed to refresh Gmail API OAuth2 access token: '.$response->body(),
                );
            }

            $token = $response->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Gmail API OAuth2 token response did not include an access_token.');
            }

            return $token;
        });
    }
}
