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
 * SRS §6.1: "Completion email carries a register CTA with the email
 * prefilled." Reviews are the concrete reason to register — a guest cannot
 * leave one (CLAUDE.md §4 permissions matrix), so the invitation says so
 * rather than asking them to sign up for its own sake.
 *
 * The CTA carries no access token: it lands on the registration form with
 * the email prefilled, and verification is what attaches the booking.
 */
class GuestBookingCompletedNotification extends Notification implements ChannelResolvable, ShouldQueue
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

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Your booking #{$this->booking->booking_number} is complete")
            ->greeting("Hi {$this->booking->contactName()},")
            ->line("{$this->booking->provider->legal_name} has completed your booking #{$this->booking->booking_number}. Thank you for using our platform.")
            ->line('Create a free account with this email address to keep your booking history, track future bookings in one place, re-book in a couple of clicks, and leave a review for this job.')
            ->action('Create my account', $this->registerUrl())
            ->line('Verifying your email address is what attaches this booking to your new account — until then nothing changes.');
    }

    private function registerUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            .'/register/customer?email='.urlencode($this->booking->contactEmail())
            .'&booking='.urlencode($this->booking->booking_number);
    }
}
