<?php

declare(strict_types=1);

namespace App\Domain\Dispute\Actions;

use App\Domain\Dispute\Enums\DisputeStatus;
use App\Domain\Dispute\Events\DisputeAssigned;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use Illuminate\Support\Facades\DB;

/**
 * Admin queue triage (SRS §12 "dispute manager: assign"). Reassignment of an
 * already-assigned dispute is allowed — only a resolved/terminal dispute is
 * locked against further assignment.
 */
final class AssignDispute implements Action
{
    public function handle(Dispute $dispute, User $admin, User $actor): Dispute
    {
        return DB::transaction(function () use ($dispute, $admin, $actor) {
            $dispute = Dispute::query()->lockForUpdate()->findOrFail($dispute->id);

            if (! in_array($dispute->status, [DisputeStatus::Open, DisputeStatus::UnderReview], true)) {
                throw new ConflictException('Only an open dispute can be assigned.', 'dispute_not_open');
            }

            $previousAssignedAdminId = $dispute->assigned_admin_id;

            $dispute->update(['assigned_admin_id' => $admin->id]);

            DisputeAssigned::dispatch($dispute, $admin, $actor, $previousAssignedAdminId);

            return $dispute->fresh();
        });
    }
}
