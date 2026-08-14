<?php

declare(strict_types=1);

namespace App\Domain\Business\Listeners;

use App\Domain\Business\Events\BusinessVerificationApproved;
use App\Domain\Notification\Notifications\VerificationApprovedNotification;
use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBusinessVerificationApprovedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(BusinessVerificationApproved $event): void
    {
        $owner = $event->business->user;

        if ($owner === null) {
            return;
        }

        $this->dispatcher->send($owner, new VerificationApprovedNotification('business'));
    }
}
