<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Listeners;

use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Freelance\Events\MilestoneStatusChanged;
use App\Domain\Freelance\Notifications\MilestoneApprovedNotification;
use App\Domain\Freelance\Notifications\MilestoneSubmittedNotification;
use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMilestoneStatusNotifications implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(MilestoneStatusChanged $event): void
    {
        $milestone = $event->milestone;
        $contract = $milestone->contract;

        if ($event->toStatus === MilestoneStatus::Submitted->value) {
            $client = $contract?->project?->client;

            if ($client !== null) {
                $this->dispatcher->send($client, new MilestoneSubmittedNotification($milestone));
            }

            return;
        }

        if ($event->toStatus === MilestoneStatus::Approved->value) {
            $freelancer = $contract?->proposal?->freelancer?->user;

            if ($freelancer !== null) {
                $this->dispatcher->send($freelancer, new MilestoneApprovedNotification($milestone));
            }
        }
    }
}
