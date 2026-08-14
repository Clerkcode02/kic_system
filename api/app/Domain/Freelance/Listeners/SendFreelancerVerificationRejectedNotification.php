<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Listeners;

use App\Domain\Freelance\Events\FreelancerVerificationRejected;
use App\Domain\Notification\Notifications\VerificationRejectedNotification;
use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendFreelancerVerificationRejectedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(FreelancerVerificationRejected $event): void
    {
        $freelancer = $event->profile->user;

        if ($freelancer === null) {
            return;
        }

        $this->dispatcher->send($freelancer, new VerificationRejectedNotification('freelancer', $event->reason));
    }
}
