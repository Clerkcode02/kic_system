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
 * SRS §6.1: the tracking link email — the only place a guest ever receives
 * a live booking access token. Sent on booking creation and on a successful
 * `/guest/bookings/lookup`.
 *
 * Mail only, by construction: the recipient is an AnonymousNotifiable, and
 * {@see \App\Domain\Notification\Services\NotificationChannelResolver}
 * resolves that to ['mail'].
 *
 * `toDatabase()` is deliberately absent — writing this to the in-app
 * notifications table would persist the plaintext token.
 */
class GuestBookingTrackingNotification extends Notification implements ChannelResolvable, ShouldQueue
{
    use Queueable;
    use ResolvesNotificationChannels;

    public function __construct(
        public readonly Booking $booking,
        private readonly string $trackingUrl,
    ) {
        $this->onQueue('notifications');
    }

    protected function notificationCategory(): NotificationCategory
    {
        return NotificationCategory::Booking;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Track your booking #{$this->booking->booking_number}")
            ->greeting("Hi {$this->booking->contactName()},")
            ->line("Your booking with {$this->booking->provider->legal_name} is confirmed as request #{$this->booking->booking_number}.")
            ->line('Use the link below to check its status, review and accept a quotation, pay, or cancel. Keep it private — anyone with this link can manage the booking.')
            ->action('Track my booking', $this->trackingUrl)
            ->line('You do not need an account. If you would like your booking history saved, you can register with this email address later and verify it — this booking will be attached automatically.');
    }
}
