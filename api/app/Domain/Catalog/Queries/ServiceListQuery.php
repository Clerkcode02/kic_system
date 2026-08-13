<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Service;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Browse/search read path — filters by category (slug, including its
 * children), PostGIS radius, and sorts, then cursor-paginates. Radius is a
 * pure WHERE filter (never an ORDER BY) so the cursor keeps working off a
 * plain, cheap-to-compare column pair.
 */
final class ServiceListQuery
{
    private const DEFAULT_RADIUS_METERS = 25_000;

    private const PER_PAGE = 20;

    /**
     * @param  array{category?: string, lat?: float, lng?: float, radius?: int, sort?: string, cursor?: string}  $filters
     * @return CursorPaginator<int, Service>
     */
    public function handle(array $filters): CursorPaginator
    {
        $query = Service::query()
            ->where('is_active', true)
            ->whereHas(
                'business',
                fn ($q) => $q->where('verification_status', BusinessVerificationStatus::Verified)
            )
            ->with([
                'category:id,name,slug',
                'business:id,legal_name,rating_avg',
            ]);

        if (! empty($filters['category'])) {
            $this->filterByCategory($query, $filters['category']);
        }

        if (isset($filters['lat'], $filters['lng'])) {
            $this->filterByRadius($query, (float) $filters['lat'], (float) $filters['lng'], (int) ($filters['radius'] ?? self::DEFAULT_RADIUS_METERS));
        }

        match ($filters['sort'] ?? 'newest') {
            'price_low' => $query->orderBy('base_price')->orderBy('id'),
            'price_high' => $query->orderByDesc('base_price')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderBy('id'),
        };

        return $query->cursorPaginate(self::PER_PAGE);
    }

    /**
     * @param  Builder<Service>  $query
     */
    private function filterByCategory(Builder $query, string $categorySlug): void
    {
        $category = Category::query()->where('slug', $categorySlug)->first();

        if ($category === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $categoryIds = [$category->id, ...$category->children()->pluck('id')];
        $query->whereIn('category_id', $categoryIds);
    }

    /**
     * @param  Builder<Service>  $query
     */
    private function filterByRadius(Builder $query, float $lat, float $lng, int $radiusMeters): void
    {
        $query->whereHas('business', function ($q) use ($lat, $lng, $radiusMeters) {
            $q->whereRaw(
                'ST_DWithin(location, ST_MakePoint(?, ?)::geography, ?)',
                [$lng, $lat, $radiusMeters]
            );
        });
    }
}
