<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Freelance\Models\Milestone;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;
use App\Policies\Concerns\GrantsAdminOversight;

class MilestonePolicy
{
    use GrantsAdminOversight;

    public function view(User $user, Milestone $milestone): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $user->can(PermissionName::MilestonesView->value);
        }

        return $user->can(PermissionName::MilestonesView->value) && $this->isPartyToContract($user, $milestone);
    }

    public function submit(User $user, Milestone $milestone): bool
    {
        return $user->can(PermissionName::MilestonesSubmit->value)
            && $milestone->contract->proposal->freelancer->user_id === $user->id;
    }

    public function approve(User $user, Milestone $milestone): bool
    {
        return $user->can(PermissionName::MilestonesApprove->value)
            && $milestone->contract->project->client_id === $user->id;
    }

    private function isPartyToContract(User $user, Milestone $milestone): bool
    {
        return $milestone->contract->project->client_id === $user->id
            || $milestone->contract->proposal->freelancer->user_id === $user->id;
    }
}
