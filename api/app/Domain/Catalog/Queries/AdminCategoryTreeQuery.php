<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Models\Category;
use Illuminate\Support\Collection;

/**
 * Admin category manager needs inactive categories too (to reactivate
 * them), unlike the public CategoryTreeQuery which only shows active ones
 * and caches the result — this is uncached and always live.
 */
final class AdminCategoryTreeQuery
{
    /**
     * @return Collection<int, Category>
     */
    public function handle(): Collection
    {
        $all = Category::query()->orderBy('sort_order')->get();

        return $this->buildLevel($all, null);
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
