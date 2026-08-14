<?php

declare(strict_types=1);

namespace App\Domain\Review\Listeners;

use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\Review\Events\ReviewReceived;
use App\Domain\Review\Notifications\ReviewReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendReviewReceivedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(ReviewReceived $event): void
    {
        $this->dispatcher->send($event->review->reviewee, new ReviewReceivedNotification($event->review));
    }
}
