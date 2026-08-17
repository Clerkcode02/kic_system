<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Notifications;

use App\Domain\Booking\Notifications\Concerns\LinksToBooking;
use App\Domain\Notification\Concerns\ResolvesNotificationChannels;
use App\Domain\Notification\Contracts\ChannelResolvable;
use App\Domain\Notification\Enums\NotificationCategory;
use App\Domain\Quotation\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotationSentNotification extends Notification implements ChannelResolvable, ShouldQueue
{
    use LinksToBooking;
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
            'total_amount' => $this->quotation->total_amount->toDecimal(),
            'message' => 'You received a new quotation.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('You received a new quotation')
            ->line('A provider sent you a quotation for '.$this->quotation->total_amount->toDecimal().' '.$this->quotation->total_amount->currency.'.')
            ->action('Review quotation', $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'New quotation',
            'body' => 'A provider sent you a quotation.',
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return $this->customerBookingUrl($this->quotation->booking);
    }
}
