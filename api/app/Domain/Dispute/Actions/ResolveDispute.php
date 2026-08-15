<?php

declare(strict_types=1);

namespace App\Domain\Dispute\Actions;

use App\Domain\Dispute\Enums\DisputeStatus;
use App\Domain\Dispute\Events\DisputeResolved;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Payment\Jobs\ReleaseMilestoneEscrowJob;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use Illuminate\Support\Facades\DB;

/**
 * Admin resolution of an open dispute (SRS §12 admin workflow — "dispute
 * manager: assign, resolve, refund authority"). When the disputed entity
 * is a Milestone whose escrow release was frozen while the dispute was
 * open (see ReleaseMilestoneEscrow's guard) and the resolution approves
 * releasing funds, this re-dispatches the escrow-release job so the freeze
 * self-heals instead of requiring a separate manual retry.
 */
final class ResolveDispute implements Action
{
    public function handle(Dispute $dispute, User $actor, string $resolutionNotes, bool $releaseEscrow = false): Dispute
    {
        return DB::transaction(function () use ($dispute, $actor, $resolutionNotes, $releaseEscrow) {
            $dispute = Dispute::query()->lockForUpdate()->findOrFail($dispute->id);

            if (! in_array($dispute->status, [DisputeStatus::Open, DisputeStatus::UnderReview], true)) {
                throw new ConflictException('Only an open dispute can be resolved.', 'dispute_not_open');
            }

            $previousStatus = $dispute->status->value;

            $dispute->update([
                'status' => DisputeStatus::Resolved,
                'resolution_notes' => $resolutionNotes,
            ]);

            DisputeResolved::dispatch($dispute, $actor, $previousStatus);

            if ($releaseEscrow
                && $dispute->disputable_type === 'milestone'
                && ($milestone = Milestone::query()->find($dispute->disputable_id)) instanceof Milestone
                && $milestone->status === MilestoneStatus::Approved
            ) {
                ReleaseMilestoneEscrowJob::dispatch($milestone->id, $actor->id);
            }

            return $dispute->fresh();
        });
    }
}
