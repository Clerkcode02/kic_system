<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Business\Models\Business;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        return $user->can(PermissionName::BusinessesView->value);
    }

    public function manage(User $user, Business $business): bool
    {
        return $user->can(PermissionName::BusinessesManage->value) && $business->user_id === $user->id;
    }

    public function approve(User $user, Business $business): bool
    {
        return $user->can(PermissionName::BusinessesApprove->value);
    }
}
