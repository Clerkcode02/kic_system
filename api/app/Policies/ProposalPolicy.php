<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Freelance\Enums\FreelancerApprovalStatus;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;
use App\Policies\Concerns\GrantsAdminOversight;

class ProposalPolicy
{
    use GrantsAdminOversight;

    public function view(User $user, Proposal $proposal): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $user->can(PermissionName::ProposalsView->value);
        }

        return $user->can(PermissionName::ProposalsView->value)
            && ($proposal->freelancer->user_id === $user->id || $proposal->project->client_id === $user->id);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->can(PermissionName::ProposalsCreate->value)
            && $user->freelancerProfile !== null
            && $user->freelancerProfile->approval_status === FreelancerApprovalStatus::Approved;
    }

    public function hire(User $user, Proposal $proposal): bool
    {
        return $user->can(PermissionName::ProposalsHire->value) && $proposal->project->client_id === $user->id;
    }
}
