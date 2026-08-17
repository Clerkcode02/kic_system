<?php

declare(strict_types=1);

namespace App\Domain\Booking\Listeners;

use App\Domain\Booking\Events\GuestBookingTokenIssued;
use App\Domain\Booking\Notifications\GuestBookingTrackingNotification;
use App\Domain\Booking\Services\BookingAccessTokenService;
use App\Domain\Notification\Services\BookingNotifiableResolver;
use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * SRS §6.1: emails the tracking link when a booking access token is minted.
 *
 * Queued like every other notification listener. That does put the
 * plaintext token in the queue payload for the duration of the job —
 * unavoidable for any emailed credential (Laravel's own password reset
 * works the same way), and materially different from persisting it: the
 * payload is transient and never written to the audit trail, the bookings
 * table, or a log line.
 */
class SendGuestBookingTrackingLink implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly BookingNotifiableResolver $notifiables,
        private readonly BookingAccessTokenService $tokens,
    ) {
    }

    public function handle(GuestBookingTokenIssued $event): void
    {
        $recipient = $this->notifiables->forCustomer($event->booking);

        if ($recipient === null) {
            return;
        }

        $this->dispatcher->send($recipient, new GuestBookingTrackingNotification(
            $event->booking,
            $this->tokens->trackingUrl($event->booking, $event->plaintextToken),
        ));
    }
}
