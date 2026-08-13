<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Models\Service;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Provider's own service list — no verification/is_active gating (unlike
 * the public browse query), since a provider must be able to see and
 * manage inactive/draft services too.
 */
final class ProviderServiceListQuery
{
    private const PER_PAGE = 20;

    /**
     * @return CursorPaginator<int, Service>
     */
    public function handle(Business $business): CursorPaginator
    {
        return Service::query()
            ->where('business_id', $business->id)
            ->with(['category:id,name,slug', 'pricingTiers'])
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
