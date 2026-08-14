<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Listeners;

use App\Domain\Freelance\Events\FreelancerHired;
use App\Domain\Freelance\Notifications\FreelancerHiredNotification;
use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendFreelancerHiredNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(FreelancerHired $event): void
    {
        $freelancer = $event->contract->proposal?->freelancer?->user;

        if ($freelancer === null) {
            return;
        }

        $this->dispatcher->send($freelancer, new FreelancerHiredNotification($event->contract));
    }
}
