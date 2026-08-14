<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Events\ProjectStatusChanged;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\StateMachines\ProjectStateMachine;
use App\Domain\User\Models\User;
use App\Support\Action;
use Illuminate\Support\Facades\DB;

/**
 * The single write path for every project status change (CLAUDE.md
 * Booking §5 precedent — "Every status change goes through the module's
 * StateMachine. Never `$model->status = x`."). $actor is nullable for
 * system-triggered transitions.
 */
final class TransitionProjectStatus implements Action
{
    public function handle(Project $project, ProjectStatus $to, ?User $actor, ?string $note = null): Project
    {
        return DB::transaction(function () use ($project, $to, $actor, $note) {
            $machine = new ProjectStateMachine($project->status);
            $from = $machine->state();
            $machine->transition($to->value);

            $project->update(['status' => $to]);

            AuditLog::create([
                'actor_id' => $actor?->id,
                'action' => 'project.status_changed',
                'auditable_type' => 'project',
                'auditable_id' => $project->id,
                'before_state' => ['status' => $from],
                'after_state' => ['status' => $to->value, 'note' => $note],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            ProjectStatusChanged::dispatch($project, $from, $to->value, $actor);

            return $project->refresh();
        });
    }
}
