<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Services;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

/**
 * Wraps the category tree cache entry. Uses Redis tagged caching in every
 * real environment; degrades to a plain untagged key when the active store
 * doesn't support tags (the `array` driver used in tests) rather than
 * throwing `BadMethodCallException` on `Cache::tags()`.
 */
final class CategoryTreeCache
{
    private const TAG = 'catalog:categories';

    private const KEY = 'catalog:categories:tree';

    /**
     * @template TCacheValue
     * @param  Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function remember(Closure $callback): mixed
    {
        if ($this->supportsTags()) {
            return Cache::tags([self::TAG])->rememberForever(self::KEY, $callback);
        }

        return Cache::rememberForever(self::KEY, $callback);
    }

    public function flush(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::TAG])->flush();

            return;
        }

        Cache::forget(self::KEY);
    }

    private function supportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }
}
