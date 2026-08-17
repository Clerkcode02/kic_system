<?php

declare(strict_types=1);

namespace App\Domain\Booking\Listeners;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Events\BookingStatusChanged;
use App\Domain\Booking\Notifications\BookingAcceptedNotification;
use App\Domain\Booking\Notifications\BookingCancelledNotification;
use App\Domain\Booking\Notifications\GuestBookingCompletedNotification;
use App\Domain\Notification\Services\BookingNotifiableResolver;
use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\User\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * BookingStatusChanged fires for every transition (CLAUDE.md Booking §5) —
 * only Accepted, Cancelled and (for guests) Completed produce a
 * notification; every other toStatus is a silent no-op here.
 *
 * The customer side resolves through {@see BookingNotifiableResolver}, so a
 * guest receives the same notifications by mail with no branch in this
 * listener (SRS §6.1).
 */
class SendBookingStatusNotifications implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly BookingNotifiableResolver $notifiables,
    ) {
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

        if ($event->toStatus === BookingStatus::Completed->value) {
            // SRS §6.1: the completion email is where a guest is invited to
            // register — with their email prefilled and an explanation that
            // verifying attaches this booking (and unlocks reviewing it,
            // which guests cannot do).
            if ($booking->isGuest()) {
                $customer = $this->notifiables->forCustomer($booking);

                if ($customer !== null) {
                    $this->dispatcher->send($customer, new GuestBookingCompletedNotification($booking));
                }
            }

            return;
        }

        if ($event->toStatus === BookingStatus::Cancelled->value) {
            $recipients = array_filter([
                $this->notifiables->forCustomer($booking),
                $booking->provider?->user,
            ]);

            // Don't notify whoever performed the cancellation of their own
            // action. Only a User can be compared by id — an anonymous
            // guest notifiable is the customer by construction, so it is
            // suppressed exactly when the guest actor cancelled.
            $actorId = $event->actor?->userId();
            $actorIsGuest = $event->actor?->isGuest() ?? false;

            $recipients = array_filter(
                $recipients,
                fn (User|AnonymousNotifiable $recipient) => match (true) {
                    $recipient instanceof AnonymousNotifiable => ! $actorIsGuest,
                    $actorId === null => true,
                    default => $recipient->id !== $actorId,
                },
            );

            if ($recipients !== []) {
                $this->dispatcher->send($recipients, new BookingCancelledNotification($booking));
            }
        }
    }
}
