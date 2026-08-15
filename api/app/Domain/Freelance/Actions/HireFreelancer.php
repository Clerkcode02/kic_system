<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\ContractStatus;
use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Enums\ProposalStatus;
use App\Domain\Freelance\Events\FreelancerHired;
use App\Domain\Freelance\Events\ProposalStatusChanged;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Freelance\StateMachines\ProjectStateMachine;
use App\Domain\Freelance\StateMachines\ProposalStateMachine;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use App\Support\PaymentsBlockedException;
use Illuminate\Support\Facades\DB;

/**
 * SRS §10: "HireFreelancer action wraps Project + Proposal update in a DB
 * transaction with a row lock (lockForUpdate) on the project to prevent a
 * race where two proposals are accepted concurrently; all other open
 * proposals are auto-transitioned to rejected with a notification."
 *
 * The project row (which already exists by the time a proposal can be
 * hired) is what gets locked — unlike DoubleBookingValidator's advisory
 * lock, which exists only because a booking's conflicting row doesn't yet
 * exist at check time. Whichever concurrent caller acquires the lock first
 * commits the hire; the second blocks until that commit, then re-reads the
 * now-InProgress project and gets a 409 instead of a second Contract.
 */
final class HireFreelancer implements Action
{
    public function handle(Proposal $proposal, User $actor): Contract
    {
        if (! $proposal->freelancer->canReceiveFunds()) {
            throw new PaymentsBlockedException(
                'This freelancer cannot receive payouts yet — Stripe Connect onboarding is incomplete.',
                'freelancer_payouts_not_enabled',
            );
        }

        return DB::transaction(function () use ($proposal, $actor) {
            $project = Project::query()->lockForUpdate()->findOrFail($proposal->project_id);

            if ($project->status !== ProjectStatus::Open) {
                throw new ConflictException(
                    'This project is no longer open for hiring.',
                    'project_not_open',
                );
            }

            $proposal = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);

            if (! in_array($proposal->status, [ProposalStatus::Submitted, ProposalStatus::Shortlisted], true)) {
                throw new ConflictException(
                    'This proposal is no longer eligible to be hired.',
                    'proposal_not_eligible',
                );
            }

            $proposalMachine = new ProposalStateMachine($proposal->status);
            $proposalMachine->transition(ProposalStatus::Accepted->value);
            $proposal->update(['status' => ProposalStatus::Accepted]);

            $contract = Contract::create([
                'project_id' => $project->id,
                'proposal_id' => $proposal->id,
                'total_amount' => $proposal->proposed_amount,
                'currency' => $proposal->currency,
                'status' => ContractStatus::Active,
            ]);

            $projectMachine = new ProjectStateMachine($project->status);
            $projectMachine->transition(ProjectStatus::InProgress->value);
            $project->update(['status' => ProjectStatus::InProgress]);

            $this->rejectOtherProposals($project, $proposal, $actor);

            FreelancerHired::dispatch($contract->fresh(['project', 'proposal']), $actor);

            return $contract->fresh(['project', 'proposal']);
        });
    }

    private function rejectOtherProposals(Project $project, Proposal $hired, User $actor): void
    {
        $siblings = Proposal::query()
            ->where('project_id', $project->id)
            ->whereKeyNot($hired->id)
            ->whereIn('status', [ProposalStatus::Submitted, ProposalStatus::Shortlisted])
            ->get();

        foreach ($siblings as $sibling) {
            $from = $sibling->status->value;

            $sibling->update(['status' => ProposalStatus::Rejected]);

            // Notification-channel delivery is Notification-module scope
            // (still unbuilt — see AppServiceProvider) — this event is what
            // a future listener would hook to actually notify the applicant,
            // and RecordAuditEntry (global Auditable listener) is what
            // records it as `proposal.auto_rejected`.
            ProposalStatusChanged::dispatch(
                $sibling,
                $from,
                ProposalStatus::Rejected->value,
                $actor,
                'Another proposal was hired.',
                'proposal.auto_rejected',
            );
        }
    }
}
