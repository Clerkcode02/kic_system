<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;

/**
 * Admin oversight is read-only by construction: this trait is only ever
 * consulted from `view`/`viewAny`-style policy methods, never from the
 * create/update/state-transition ones. Those stay gated purely by the
 * seeded permission set, which never grants admins the participant-side
 * actions (booking.create, proposals.create, …) — see SRS §6, "Admin"
 * column is ❌ for every transactional action.
 */
trait GrantsAdminOversight
{
    private function isPlatformAdmin(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::SuperAdmin->value]);
    }
}
