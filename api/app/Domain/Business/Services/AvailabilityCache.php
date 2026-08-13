<?php

declare(strict_types=1);

namespace App\Domain\Business\Services;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

/**
 * Provider availability cached per-provider-per-day with a 5-minute TTL,
 * busted on any booking write for that provider/date (CLAUDE.md
 * provider-management spec). Degrades to plain untagged keys on stores that
 * don't support tags (the `array` driver used in tests), same pattern as
 * {@see \App\Domain\Catalog\Services\CategoryTreeCache}.
 */
final class AvailabilityCache
{
    private const TTL_MINUTES = 5;

    /**
     * @template TCacheValue
     * @param  Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function remember(string $businessId, string $date, Closure $callback): mixed
    {
        $ttl = now()->addMinutes(self::TTL_MINUTES);

        if ($this->supportsTags()) {
            return Cache::tags([$this->tag($businessId)])->remember($this->key($businessId, $date), $ttl, $callback);
        }

        return Cache::remember($this->key($businessId, $date), $ttl, $callback);
    }

    public function forget(string $businessId, string $date): void
    {
        if ($this->supportsTags()) {
            Cache::tags([$this->tag($businessId)])->forget($this->key($businessId, $date));

            return;
        }

        Cache::forget($this->key($businessId, $date));
    }

    /**
     * Invalidates every cached day for a business — used when the weekly
     * schedule or overrides change, since that can affect any date.
     */
    public function flushForBusiness(string $businessId): void
    {
        if ($this->supportsTags()) {
            Cache::tags([$this->tag($businessId)])->flush();
        }
    }

    private function key(string $businessId, string $date): string
    {
        return "availability:{$businessId}:{$date}";
    }

    private function tag(string $businessId): string
    {
        return "availability:business:{$businessId}";
    }

    private function supportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }
}
