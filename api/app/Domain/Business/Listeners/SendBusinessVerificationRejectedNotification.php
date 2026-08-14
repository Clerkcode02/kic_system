<?php

declare(strict_types=1);

namespace App\Domain\Business\Listeners;

use App\Domain\Business\Events\BusinessVerificationRejected;
use App\Domain\Notification\Notifications\VerificationRejectedNotification;
use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBusinessVerificationRejectedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(BusinessVerificationRejected $event): void
    {
        $owner = $event->business->user;

        if ($owner === null) {
            return;
        }

        $this->dispatcher->send($owner, new VerificationRejectedNotification('business', $event->reason));
    }
}
