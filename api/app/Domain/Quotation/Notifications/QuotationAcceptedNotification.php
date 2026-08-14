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

class QuotationAcceptedNotification extends Notification implements ChannelResolvable, ShouldQueue
{
    use Queueable;
    use ResolvesNotificationChannels;

    public function __construct(public readonly Quotation $quotation)
    {
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
            'message' => 'Your quotation was accepted and paid.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your quotation was accepted')
            ->line('The customer accepted your quotation and payment has been confirmed.')
            ->action('View booking', $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Quotation accepted',
            'body' => 'Your quotation was accepted and paid.',
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/provider/bookings/{$this->quotation->booking_id}";
    }
}
