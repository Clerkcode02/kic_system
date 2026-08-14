<?php

declare(strict_types=1);

namespace App\Domain\Notification\Notifications;

use App\Domain\Notification\Concerns\ResolvesNotificationChannels;
use App\Domain\Notification\Contracts\ChannelResolvable;
use App\Domain\Notification\Enums\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationRejectedNotification extends Notification implements ChannelResolvable, ShouldQueue
{
    use Queueable;
    use ResolvesNotificationChannels;

    public function __construct(
        public readonly string $subjectLabel,
        public readonly ?string $reason,
    ) {
        $this->onQueue('notifications');
    }

    protected function notificationCategory(): NotificationCategory
    {
        return NotificationCategory::Verification;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'reason' => $this->reason,
            'message' => "Your {$this->subjectLabel} verification was rejected.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject('Verification rejected')
            ->line("Your {$this->subjectLabel} verification was rejected.");

        if ($this->reason !== null) {
            $message->line("Reason: {$this->reason}");
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Verification rejected',
            'body' => $this->reason ?? "Your {$this->subjectLabel} verification was rejected.",
        ];
    }
}
