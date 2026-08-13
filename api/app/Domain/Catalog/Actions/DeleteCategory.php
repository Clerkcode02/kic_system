<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Events\CategoryTreeChanged;
use App\Domain\Catalog\Models\Category;
use App\Support\ConflictException;

class DeleteCategory
{
    public function handle(Category $category): void
    {
        if ($category->children()->exists()) {
            throw new ConflictException('Cannot delete a category that has subcategories.', 'category_has_children');
        }

        if ($category->services()->exists() || $category->projects()->exists()) {
            throw new ConflictException('Cannot delete a category that is in use.', 'category_in_use');
        }

        $category->delete();

        CategoryTreeChanged::dispatch();
    }
}
