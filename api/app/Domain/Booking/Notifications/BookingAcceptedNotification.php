<?php

declare(strict_types=1);

namespace App\Domain\Booking\Notifications;

use App\Domain\Booking\Models\Booking;
use App\Domain\Notification\Concerns\ResolvesNotificationChannels;
use App\Domain\Notification\Contracts\ChannelResolvable;
use App\Domain\Notification\Enums\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the provider once the customer has accepted the quotation and
 * paid — the booking transitioned into BookingStatus::Accepted.
 */
class BookingAcceptedNotification extends Notification implements ChannelResolvable, ShouldQueue
{
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
            'message' => "Booking #{$this->booking->booking_number} was accepted and paid.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Booking #{$this->booking->booking_number} accepted")
            ->line('The customer accepted your quotation and payment has been confirmed.')
            ->action('View booking', $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Booking accepted',
            'body' => "Booking #{$this->booking->booking_number} is confirmed and paid.",
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/provider/bookings/{$this->booking->id}";
    }
}
