<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\User\Models\User;
use App\Support\Action;

/**
 * SRS §10: submitted --> disputed on client rejection; the freelancer
 * resubmits via {@see SubmitMilestone}, which clears `rejection_reason`.
 */
final class RejectMilestone implements Action
{
    public function __construct(private readonly TransitionMilestoneStatus $transition)
    {
    }

    public function handle(Milestone $milestone, User $actor, string $reason): Milestone
    {
        $milestone = $this->transition->handle($milestone, MilestoneStatus::Disputed, $actor, $reason);
        $milestone->update(['rejection_reason' => $reason]);

        return $milestone;
    }
}
