<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Services\CategoryTreeCache;
use Illuminate\Support\Collection;

/**
 * Builds the full active category tree in a single query (no recursive
 * CTE, no N+1) by fetching every active category once and grouping by
 * `parent_id` in memory, then caches the assembled tree.
 */
final class CategoryTreeQuery
{
    public function __construct(
        private readonly CategoryTreeCache $cache,
    ) {
    }

    /**
     * @return Collection<int, Category>
     */
    public function handle(): Collection
    {
        return $this->cache->remember(function (): Collection {
            $all = Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            return $this->buildLevel($all, null);
        });
    }

    /**
     * @param  Collection<int, Category>  $all
     * @return Collection<int, Category>
     */
    private function buildLevel(Collection $all, ?string $parentId): Collection
    {
        return $all->where('parent_id', $parentId)->values()->map(function (Category $category) use ($all) {
            $category->setRelation('children', $this->buildLevel($all, $category->id));

            return $category;
        });
    }
}
