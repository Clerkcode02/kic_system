<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Models\Service;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ServicesView->value);
    }

    public function view(User $user, Service $service): bool
    {
        return $user->can(PermissionName::ServicesView->value);
    }

    public function create(User $user, Business $business): bool
    {
        return $user->can(PermissionName::ServicesManage->value) && $business->user_id === $user->id;
    }

    public function manage(User $user, Service $service): bool
    {
        return $user->can(PermissionName::ServicesManage->value) && $service->business->user_id === $user->id;
    }
}
