<?php

declare(strict_types=1);

namespace App\Domain\Platform\Events;

use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SRS §12 admin workflow — editing a `platform_settings` row is an admin
 * override on a business-rule constant (fee percentages, expiry windows,
 * refund thresholds, ...), so it belongs in the audit trail like any other
 * admin override.
 */
class PlatformSettingUpdated implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PlatformSetting $setting,
        public readonly User $actor,
        public readonly ?string $previousValue,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->actor->id;
    }

    public function auditAction(): string
    {
        return 'platform_setting.updated';
    }

    public function auditableType(): string
    {
        return 'platform_setting';
    }

    public function auditableId(): string
    {
        return $this->setting->id;
    }

    public function auditBeforeState(): ?array
    {
        return ['key' => $this->setting->key, 'value' => $this->previousValue];
    }

    public function auditAfterState(): ?array
    {
        return ['key' => $this->setting->key, 'value' => $this->setting->value];
    }
}
