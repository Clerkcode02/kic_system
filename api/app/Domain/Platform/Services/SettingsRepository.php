<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

/**
 * CLAUDE.md §5 "platform_settings: typed key/value store ... Redis-cached,
 * admin-editable". Every Action/Job that previously ran its own
 * `PlatformSetting::query()->where('key', ...)->first()` on every call now
 * goes through here instead, so a hot path (e.g. QuotationTotalCalculator,
 * called on every quotation) doesn't hit Postgres per request.
 */
final class SettingsRepository
{
    private const CACHE_PREFIX = 'platform_settings:';

    private const CACHE_TTL_SECONDS = 3600;

    /**
     * @param  string|int|float|bool|array<mixed>|null  $default
     * @return string|int|float|bool|array<mixed>|null
     */
    public function get(string $key, string|int|float|bool|array|null $default = null): string|int|float|bool|array|null
    {
        return Cache::remember(
            self::CACHE_PREFIX.$key,
            self::CACHE_TTL_SECONDS,
            function () use ($key, $default) {
                $setting = PlatformSetting::query()->where('key', $key)->first();

                if ($setting === null) {
                    return $default;
                }

                return $setting->typedValue;
            },
        );
    }

    public function set(string $key, string $value, ?string $type = null, ?string $description = null): PlatformSetting
    {
        $existing = PlatformSetting::query()->where('key', $key)->first();

        $setting = PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type ?? ($existing === null ? 'string' : $existing->type),
                'description' => $description ?? $existing?->description,
            ],
        );

        Cache::forget(self::CACHE_PREFIX.$key);

        return $setting;
    }

    public function forget(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX.$key);
    }
}
