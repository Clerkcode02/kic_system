<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;

/**
 * SRS §12 admin workflow — "platform fee config: global default +
 * per-category overrides" is one of the admin-editable surfaces. Every
 * configurable business-rule constant lives in `platform_settings`
 * (CLAUDE.md §5), gated here by a single permission rather than the
 * per-ownership checks other policies need.
 */
class PlatformSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PlatformSettingsManage->value);
    }

    public function update(User $user, PlatformSetting $setting): bool
    {
        return $user->can(PermissionName::PlatformSettingsManage->value);
    }
}
