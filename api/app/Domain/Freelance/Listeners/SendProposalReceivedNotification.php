<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Listeners;

use App\Domain\Freelance\Events\ProposalSubmitted;
use App\Domain\Freelance\Notifications\ProposalReceivedNotification;
use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendProposalReceivedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(ProposalSubmitted $event): void
    {
        $client = $event->proposal->project?->client;

        if ($client === null) {
            return;
        }

        $this->dispatcher->send($client, new ProposalReceivedNotification($event->proposal));
    }
}
