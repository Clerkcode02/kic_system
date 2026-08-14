<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Payment\Jobs\ReleaseMilestoneEscrowJob;
use App\Domain\User\Models\User;
use App\Support\Action;

/**
 * Records the client's approval and queues escrow release. Money never
 * moves inline in the request/response cycle — ReleaseMilestoneEscrowJob
 * (queue: payments) creates the Stripe Transfer and completes the contract
 * once all milestones are paid (CLAUDE.md §5 "Payments").
 */
final class ApproveMilestone implements Action
{
    public function __construct(private readonly TransitionMilestoneStatus $transition)
    {
    }

    public function handle(Milestone $milestone, User $actor): Milestone
    {
        $milestone = $this->transition->handle($milestone, MilestoneStatus::Approved, $actor, 'Milestone approved by client.');

        ReleaseMilestoneEscrowJob::dispatch($milestone->id, $actor->id);

        return $milestone;
    }
}
