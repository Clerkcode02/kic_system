<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Models\Service;

/**
 * Shared by the service detail and pricing-estimate endpoints — both need
 * the same eager-loaded shape (category, business, ordered pricing tiers).
 */
final class ServiceDetailQuery
{
    public function handle(string $serviceId): Service
    {
        return Service::query()
            ->where('is_active', true)
            ->with([
                'category',
                'business',
                'pricingTiers' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->findOrFail($serviceId);
    }
}
