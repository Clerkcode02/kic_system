<?php

declare(strict_types=1);

namespace App\Domain\Booking\Notifications;

use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Notifications\Concerns\LinksToBooking;
use App\Domain\Notification\Concerns\ResolvesNotificationChannels;
use App\Domain\Notification\Contracts\ChannelResolvable;
use App\Domain\Notification\Enums\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCancelledNotification extends Notification implements ChannelResolvable, ShouldQueue
{
    use LinksToBooking;
    use Queueable;
    use ResolvesNotificationChannels;

    public function __construct(public readonly Booking $booking)
    {
        $this->onQueue('notifications');
    }

    protected function notificationCategory(): NotificationCategory
    {
        return NotificationCategory::Booking;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'message' => "Booking #{$this->booking->booking_number} was cancelled.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Booking #{$this->booking->booking_number} cancelled")
            ->line('This booking has been cancelled.')
            ->action('View booking', $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Booking cancelled',
            'body' => "Booking #{$this->booking->booking_number} was cancelled.",
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return $this->customerBookingUrl($this->booking);
    }
}
