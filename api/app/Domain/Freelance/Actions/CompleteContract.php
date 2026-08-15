<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\ContractStatus;
use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Events\ContractCompleted;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\StateMachines\ContractStateMachine;
use App\Domain\User\Models\User;
use App\Support\Action;
use Illuminate\Support\Facades\DB;

/**
 * SRS §10: "InProgress --> Completed: all milestones approved & paid."
 * Marks a single milestone `paid` and, if it was the contract's last unpaid
 * milestone, completes the contract and its project. This is the action
 * Phase 7's `ReleaseMilestoneEscrow` will call once a milestone's Stripe
 * transfer succeeds (CLAUDE.md §5 "Payments" — money never moves before
 * approval, and this action never initiates a transfer itself).
 */
final class CompleteContract implements Action
{
    public function __construct(
        private readonly TransitionMilestoneStatus $milestoneTransition,
        private readonly TransitionProjectStatus $projectTransition,
    ) {
    }

    public function handle(Milestone $milestone, ?User $actor = null): Contract
    {
        return DB::transaction(function () use ($milestone, $actor) {
            $milestone = $this->milestoneTransition->handle($milestone, MilestoneStatus::Paid, $actor, 'Milestone escrow released.');

            $contract = Contract::query()->lockForUpdate()->findOrFail($milestone->contract_id);
            $allPaid = $contract->milestones()->where('status', '!=', MilestoneStatus::Paid->value)->doesntExist();

            if ($allPaid && $contract->status === ContractStatus::Active) {
                $machine = new ContractStateMachine($contract->status);
                $from = $machine->state();
                $machine->transition(ContractStatus::Completed->value);

                $contract->update(['status' => ContractStatus::Completed]);

                ContractCompleted::dispatch($contract->fresh(), $actor, $from);

                $project = $contract->project;

                if ($project->status === ProjectStatus::InProgress) {
                    $this->projectTransition->handle($project, ProjectStatus::Completed, $actor, 'All milestones paid.');
                }
            }

            return $contract->fresh(['milestones']);
        });
    }
}
