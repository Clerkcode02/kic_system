<?php

declare(strict_types=1);

namespace App\Domain\Payment\Listeners;

use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\Payment\Events\PayoutCompleted;
use App\Domain\Payment\Notifications\PayoutCompletedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPayoutCompletedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(PayoutCompleted $event): void
    {
        $owner = $event->payout->provider?->user;

        if ($owner === null) {
            return;
        }

        $this->dispatcher->send($owner, new PayoutCompletedNotification($event->payout));
    }
}
