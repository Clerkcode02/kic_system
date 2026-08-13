<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Listeners;

use App\Domain\Catalog\Events\CategoryTreeChanged;
use App\Domain\Catalog\Services\CategoryTreeCache;

/**
 * Runs synchronously (not queued) — the write request that triggered this
 * must see a fresh tree on its own next read, and queuing would leave a
 * window where a fast subsequent GET reads the stale cached tree.
 */
class FlushCategoryTreeCache
{
    public function __construct(
        private readonly CategoryTreeCache $cache,
    ) {
    }

    public function handle(CategoryTreeChanged $event): void
    {
        $this->cache->flush();
    }
}
