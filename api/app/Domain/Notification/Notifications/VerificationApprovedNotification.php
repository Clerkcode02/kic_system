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

/**
 * Shared by both Business (provider) and FreelancerProfile verification —
 * the entity being verified differs, but the notification shape doesn't,
 * so this lives in the Notification module rather than being duplicated
 * per domain (see App\Domain\Business\Actions\ApproveBusinessVerification
 * and App\Domain\Freelance\Actions\ApproveFreelancerVerification).
 */
class VerificationApprovedNotification extends Notification implements ChannelResolvable, ShouldQueue
{
    use Queueable;
    use ResolvesNotificationChannels;

    public function __construct(public readonly string $subjectLabel)
    {
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
            'message' => "Your {$this->subjectLabel} verification was approved. You can now accept bookings.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Verification approved')
            ->line("Your {$this->subjectLabel} verification was approved.")
            ->line('You can now start accepting work on the platform.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Verification approved',
            'body' => "Your {$this->subjectLabel} verification was approved.",
        ];
    }
}
