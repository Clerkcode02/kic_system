<?php

declare(strict_types=1);

namespace App\Domain\Booking\Listeners;

use App\Domain\Booking\Events\BookingCreated;
use App\Domain\Booking\Notifications\BookingCreatedNotification;
use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingCreatedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(BookingCreated $event): void
    {
        $provider = $event->booking->provider?->user;

        if ($provider === null) {
            return;
        }

        $this->dispatcher->send($provider, new BookingCreatedNotification($event->booking));
    }
}
