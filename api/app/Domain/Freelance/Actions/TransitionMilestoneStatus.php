<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Freelance\Events\MilestoneStatusChanged;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\StateMachines\MilestoneStateMachine;
use App\Domain\User\Models\User;
use App\Support\Action;
use Illuminate\Support\Facades\DB;

/**
 * The single write path for every milestone status change (CLAUDE.md §5 —
 * "Every status change goes through the module's StateMachine. Never
 * `$model->status = x`.").
 */
final class TransitionMilestoneStatus implements Action
{
    public function handle(Milestone $milestone, MilestoneStatus $to, ?User $actor, ?string $note = null): Milestone
    {
        return DB::transaction(function () use ($milestone, $to, $actor, $note) {
            $machine = new MilestoneStateMachine($milestone->status);
            $from = $machine->state();
            $machine->transition($to->value);

            $milestone->update(['status' => $to]);

            MilestoneStatusChanged::dispatch($milestone, $from, $to->value, $actor, $note);

            return $milestone->refresh();
        });
    }
}
