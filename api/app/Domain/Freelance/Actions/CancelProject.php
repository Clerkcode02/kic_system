<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Models\Project;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;

/**
 * SRS §10: Open --> Cancelled is a free client-initiated withdrawal before
 * any hire exists. InProgress --> Cancelled is drawn as "mutual/dispute" —
 * mirroring CancelBooking's precedent for its own admin-mediated exceptional
 * edge, cancelling a project that already has a Contract running is
 * restricted to an administrator here rather than left to either party
 * unilaterally walking away from a live engagement.
 */
final class CancelProject implements Action
{
    public function __construct(private readonly TransitionProjectStatus $transition)
    {
    }

    public function handle(Project $project, User $actor): Project
    {
        $isAdmin = $actor->hasAnyRole([RoleName::Admin->value, RoleName::SuperAdmin->value]);

        if ($project->status === ProjectStatus::InProgress && ! $isAdmin) {
            throw new ConflictException(
                'A project with an active contract can only be cancelled by an administrator.',
                'exceptional_cancellation_requires_admin',
            );
        }

        return $this->transition->handle($project, ProjectStatus::Cancelled, $actor, 'Project cancelled.');
    }
}
