<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Freelance\Models\Contract;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;
use App\Policies\Concerns\GrantsAdminOversight;

class ContractPolicy
{
    use GrantsAdminOversight;

    public function view(User $user, Contract $contract): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $user->can(PermissionName::ContractsView->value);
        }

        return $user->can(PermissionName::ContractsView->value)
            && ($contract->project->client_id === $user->id || $contract->proposal->freelancer->user_id === $user->id);
    }
}
