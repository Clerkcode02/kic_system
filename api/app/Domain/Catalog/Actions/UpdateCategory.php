<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Events\CategoryTreeChanged;
use App\Domain\Catalog\Models\Category;
use App\Support\ConflictException;

class UpdateCategory
{
    private const MAX_DEPTH = 50;

    /**
     * @param  array{parent_id?: ?string, name?: string, slug?: ?string, icon?: ?string, is_active?: bool, sort_order?: int}  $data
     */
    public function handle(Category $category, array $data): Category
    {
        if (array_key_exists('parent_id', $data)) {
            $this->assertNoCycle($category, $data['parent_id']);
        }

        $category->fill($data);
        $category->save();

        CategoryTreeChanged::dispatch();

        return $category;
    }

    private function assertNoCycle(Category $category, ?string $newParentId): void
    {
        if ($newParentId === null) {
            return;
        }

        if ($newParentId === $category->id) {
            throw new ConflictException('A category cannot be its own parent.', 'category_cycle');
        }

        $current = Category::find($newParentId);
        $depth = 0;

        while ($current !== null && $depth < self::MAX_DEPTH) {
            if ($current->id === $category->id) {
                throw new ConflictException('Cannot move a category under its own descendant.', 'category_cycle');
            }

            $current = $current->parent_id !== null ? Category::find($current->parent_id) : null;
            $depth++;
        }
    }
}
