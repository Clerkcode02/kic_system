<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Services\ServicePricingCache;
use App\Support\Action;

/**
 * Services are deactivated, not hard-deleted — bookings hold a non-nullable
 * FK to services, so a real delete would either cascade-destroy booking
 * history or fail the constraint outright.
 */
class DeactivateService implements Action
{
    public function __construct(
        private readonly ServicePricingCache $cache,
    ) {
    }

    public function handle(Service $service): Service
    {
        $service->update(['is_active' => false]);

        // ServiceDetailQuery filters on is_active — without this, a
        // service cached while active would keep serving as findable/active
        // from cache after deactivation, until it happened to be evicted.
        $this->cache->forget($service->id);

        return $service;
    }
}
