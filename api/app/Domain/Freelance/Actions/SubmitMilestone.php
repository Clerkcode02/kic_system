<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\ContractStatus;
use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Models\Deliverable;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use Illuminate\Validation\ValidationException;

/**
 * SRS §19: "no deliverables after cancellation" — a milestone can only be
 * submitted while its contract is active and the project is in progress.
 */
final class SubmitMilestone implements Action
{
    public function __construct(private readonly TransitionMilestoneStatus $transition)
    {
    }

    /**
     * @param  list<string>  $deliverableIds
     */
    public function handle(Milestone $milestone, User $actor, array $deliverableIds): Milestone
    {
        $contract = $milestone->contract;

        if ($contract->status !== ContractStatus::Active || $contract->project->status !== ProjectStatus::InProgress) {
            throw new ConflictException(
                'Deliverables cannot be submitted once the project is no longer in progress.',
                'project_not_active',
            );
        }

        if ($deliverableIds === []) {
            throw ValidationException::withMessages([
                'deliverable_ids' => 'At least one deliverable is required to submit a milestone.',
            ]);
        }

        $matched = Deliverable::query()
            ->where('milestone_id', $milestone->id)
            ->whereIn('id', $deliverableIds)
            ->count();

        if ($matched !== count($deliverableIds)) {
            throw ValidationException::withMessages([
                'deliverable_ids' => 'One or more deliverables do not belong to this milestone.',
            ]);
        }

        $note = $milestone->status === MilestoneStatus::Disputed ? 'Milestone resubmitted with revised deliverables.' : 'Milestone submitted with deliverables.';
        $milestone = $this->transition->handle($milestone, MilestoneStatus::Submitted, $actor, $note);
        $milestone->update(['rejection_reason' => null]);

        return $milestone;
    }
}
