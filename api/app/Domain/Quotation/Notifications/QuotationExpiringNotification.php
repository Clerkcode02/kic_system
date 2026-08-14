<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Notifications;

use App\Domain\Notification\Concerns\ResolvesNotificationChannels;
use App\Domain\Notification\Contracts\ChannelResolvable;
use App\Domain\Notification\Enums\NotificationCategory;
use App\Domain\Quotation\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * SRS §9 reminder cadence — fired at T-24h and T-2h before valid_until.
 */
class QuotationExpiringNotification extends Notification implements ChannelResolvable, ShouldQueue
{
    use Queueable;
    use ResolvesNotificationChannels;

    public function __construct(
        public readonly Quotation $quotation,
        public readonly int $hoursBeforeExpiry,
    ) {
        $this->onQueue('notifications');
    }

    protected function notificationCategory(): NotificationCategory
    {
        return NotificationCategory::Quotation;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'quotation_id' => $this->quotation->id,
            'booking_id' => $this->quotation->booking_id,
            'hours_before_expiry' => $this->hoursBeforeExpiry,
            'message' => "Your quotation expires in about {$this->hoursBeforeExpiry} hours.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your quotation is expiring soon')
            ->line("This quotation expires in about {$this->hoursBeforeExpiry} hours.")
            ->action('Review quotation', $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Quotation expiring soon',
            'body' => "Expires in about {$this->hoursBeforeExpiry} hours.",
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/quotations/{$this->quotation->id}";
    }
}
