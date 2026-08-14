<?php

declare(strict_types=1);

namespace App\Domain\Booking\Listeners;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Events\BookingStatusChanged;
use App\Domain\Booking\Notifications\BookingAcceptedNotification;
use App\Domain\Booking\Notifications\BookingCancelledNotification;
use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * BookingStatusChanged fires for every transition (CLAUDE.md Booking §5) —
 * only Accepted and Cancelled produce a notification (SRS §11); every
 * other toStatus is a silent no-op here.
 */
class SendBookingStatusNotifications implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(BookingStatusChanged $event): void
    {
        $booking = $event->booking;

        if ($event->toStatus === BookingStatus::Accepted->value) {
            $provider = $booking->provider?->user;

            if ($provider !== null) {
                $this->dispatcher->send($provider, new BookingAcceptedNotification($booking));
            }

            return;
        }

        if ($event->toStatus === BookingStatus::Cancelled->value) {
            $recipients = array_filter([
                $booking->customer,
                $booking->provider?->user,
            ]);

            // Don't notify whoever performed the cancellation of their own action.
            $recipients = array_filter(
                $recipients,
                fn ($recipient) => $event->actor === null || $recipient->id !== $event->actor->id,
            );

            if ($recipients !== []) {
                $this->dispatcher->send($recipients, new BookingCancelledNotification($booking));
            }
        }
    }
}
