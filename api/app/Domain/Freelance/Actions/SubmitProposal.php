<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\FreelancerApprovalStatus;
use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Enums\ProposalStatus;
use App\Domain\Freelance\Events\ProposalSubmitted;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS §19: one proposal per freelancer per project (also enforced by a DB
 * unique constraint on [project_id, freelancer_id] as a last line of
 * defence against a genuine race — this pre-check is what turns that race
 * into a clean 422 instead of a raw constraint-violation 500 in the common
 * case); only Approved freelancers may submit (ProposalPolicy::create also
 * checks this, but the Action re-verifies since Policy checks can't see a
 * concurrent approval-status change mid-request).
 */
final class SubmitProposal implements Action
{
    /**
     * @param  array{proposed_amount: string|float, cover_letter: string, delivery_days: int}  $data
     */
    public function handle(Project $project, User $freelancerUser, array $data): Proposal
    {
        $freelancerProfile = $freelancerUser->freelancerProfile;

        if ($freelancerProfile === null || $freelancerProfile->approval_status !== FreelancerApprovalStatus::Approved) {
            throw ValidationException::withMessages([
                'freelancer' => 'Only approved freelancers may submit proposals.',
            ]);
        }

        if ($project->status !== ProjectStatus::Open) {
            throw ValidationException::withMessages([
                'project_id' => 'This project is no longer accepting proposals.',
            ]);
        }

        $alreadyApplied = Proposal::query()
            ->where('project_id', $project->id)
            ->where('freelancer_id', $freelancerProfile->id)
            ->exists();

        if ($alreadyApplied) {
            throw ValidationException::withMessages([
                'project_id' => 'You have already submitted a proposal for this project.',
            ]);
        }

        $proposedAmount = Money::fromDecimal((string) $data['proposed_amount'], $project->currency);

        if ($proposedAmount->minorUnits <= 0) {
            throw ValidationException::withMessages([
                'proposed_amount' => 'The proposed amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($project, $freelancerProfile, $data, $proposedAmount) {
            $proposal = Proposal::create([
                'project_id' => $project->id,
                'freelancer_id' => $freelancerProfile->id,
                'proposed_amount' => $proposedAmount,
                'currency' => $project->currency,
                'cover_letter' => $data['cover_letter'],
                'delivery_days' => $data['delivery_days'],
                'status' => ProposalStatus::Submitted,
            ]);

            ProposalSubmitted::dispatch($proposal);

            return $proposal->fresh(['project', 'freelancer']);
        });
    }
}
