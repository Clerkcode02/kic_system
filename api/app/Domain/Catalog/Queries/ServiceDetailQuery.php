<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Services\ServicePricingCache;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Shared by the service detail and pricing-estimate endpoints — both need
 * the same eager-loaded shape (category, business, ordered pricing tiers).
 * SRS §18: cached per service, busted on any write to the service or its
 * tiers (see ServicePricingCache) — this is the read path every one of
 * those cached rows exists to serve.
 */
final class ServiceDetailQuery
{
    public function __construct(
        private readonly ServicePricingCache $cache,
    ) {
    }

    public function handle(string $serviceId): Service
    {
        $service = $this->cache->remember(
            $serviceId,
            fn () => Service::query()
                ->where('is_active', true)
                ->with([
                    'category',
                    'business',
                    'pricingTiers' => fn ($q) => $q->orderBy('sort_order'),
                ])
                ->find($serviceId),
        );

        if ($service === null) {
            throw (new ModelNotFoundException())->setModel(Service::class, [$serviceId]);
        }

        return $service;
    }
}
