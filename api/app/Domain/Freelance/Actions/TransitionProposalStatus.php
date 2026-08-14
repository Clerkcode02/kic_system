<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Freelance\Enums\ProposalStatus;
use App\Domain\Freelance\Events\ProposalStatusChanged;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Freelance\StateMachines\ProposalStateMachine;
use App\Domain\User\Models\User;
use App\Support\Action;
use Illuminate\Support\Facades\DB;

final class TransitionProposalStatus implements Action
{
    public function handle(Proposal $proposal, ProposalStatus $to, ?User $actor, ?string $note = null): Proposal
    {
        return DB::transaction(function () use ($proposal, $to, $actor, $note) {
            $machine = new ProposalStateMachine($proposal->status);
            $from = $machine->state();
            $machine->transition($to->value);

            $proposal->update(['status' => $to]);

            AuditLog::create([
                'actor_id' => $actor?->id,
                'action' => 'proposal.status_changed',
                'auditable_type' => 'proposal',
                'auditable_id' => $proposal->id,
                'before_state' => ['status' => $from],
                'after_state' => ['status' => $to->value, 'note' => $note],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            ProposalStatusChanged::dispatch($proposal, $from, $to->value, $actor);

            return $proposal->refresh();
        });
    }
}
