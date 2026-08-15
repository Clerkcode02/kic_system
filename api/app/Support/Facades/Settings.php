<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\Platform\Services\SettingsRepository;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string|int|float|bool|array<mixed>|null get(string $key, string|int|float|bool|array<mixed>|null $default = null)
 * @method static PlatformSetting set(string $key, string $value, ?string $type = null, ?string $description = null)
 * @method static void forget(string $key)
 *
 * @see SettingsRepository
 */
final class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsRepository::class;
    }
}
