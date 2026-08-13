<?php

declare(strict_types=1);

namespace App\Domain\User\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $id = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());

        // Sign against the actual API route (the "signed" middleware verifies
        // against this exact path), then hand the same expires/signature
        // query pair to the SPA — there are no server-rendered views, so the
        // clicked link must land on a frontend route, not the API directly.
        $signedApiUrl = URL::temporarySignedRoute(
            'auth.verification.verify',
            now()->addMinutes(60),
            ['id' => $id, 'hash' => $hash],
        );

        $query = (string) parse_url($signedApiUrl, PHP_URL_QUERY);
        $verifyUrl = sprintf(
            '%s/verify-email/%s/%s?%s',
            rtrim((string) config('app.frontend_url'), '/'),
            $id,
            $hash,
            $query,
        );

        return (new MailMessage())
            ->subject('Verify your email address')
            ->line('Please click the button below to verify your email address.')
            ->action('Verify Email Address', $verifyUrl)
            ->line('This verification link will expire in 60 minutes.');
    }
}
