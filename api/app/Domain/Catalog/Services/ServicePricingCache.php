<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Services;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

/**
 * Caches the service-detail read (service + ordered pricing tiers) per
 * service, busted on any write to that service or its tiers. Same pattern
 * as {@see \App\Domain\Catalog\Services\CategoryTreeCache} and
 * {@see \App\Domain\Business\Services\AvailabilityCache}: Redis tagged
 * caching in every real environment, degrades to a plain untagged key on
 * stores that don't support tags (the `array` driver used in tests).
 */
final class ServicePricingCache
{
    /**
     * @template TCacheValue
     * @param  Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function remember(string $serviceId, Closure $callback): mixed
    {
        if ($this->supportsTags()) {
            return Cache::tags([$this->tag($serviceId)])->rememberForever($this->key($serviceId), $callback);
        }

        return Cache::rememberForever($this->key($serviceId), $callback);
    }

    public function forget(string $serviceId): void
    {
        if ($this->supportsTags()) {
            Cache::tags([$this->tag($serviceId)])->flush();

            return;
        }

        Cache::forget($this->key($serviceId));
    }

    private function key(string $serviceId): string
    {
        return "catalog:services:{$serviceId}:detail";
    }

    private function tag(string $serviceId): string
    {
        return "catalog:service:{$serviceId}";
    }

    private function supportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }
}
