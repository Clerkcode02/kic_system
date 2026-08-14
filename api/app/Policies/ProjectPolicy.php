<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Freelance\Models\Project;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;
use App\Policies\Concerns\GrantsAdminOversight;

class ProjectPolicy
{
    use GrantsAdminOversight;

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ProjectsView->value);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->can(PermissionName::ProjectsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ProjectsCreate->value);
    }

    public function update(User $user, Project $project): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return false;
        }

        return $user->can(PermissionName::ProjectsUpdate->value) && $project->client_id === $user->id;
    }

    public function cancel(User $user, Project $project): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        return $user->can(PermissionName::ProjectsUpdate->value) && $project->client_id === $user->id;
    }

    /**
     * Proposals for a project are visible to its owning client (and, via
     * admin oversight, to admins) — not to other freelancers competing
     * for the same work.
     */
    public function viewProposals(User $user, Project $project): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $user->can(PermissionName::ProposalsView->value);
        }

        return $user->can(PermissionName::ProposalsView->value) && $project->client_id === $user->id;
    }
}
