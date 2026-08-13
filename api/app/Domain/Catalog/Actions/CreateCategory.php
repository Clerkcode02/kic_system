<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Events\CategoryTreeChanged;
use App\Domain\Catalog\Models\Category;
use Illuminate\Support\Str;

class CreateCategory
{
    /**
     * @param  array{parent_id?: ?string, name: string, slug?: ?string, icon?: ?string, is_active?: bool, sort_order?: int}  $data
     */
    public function handle(array $data): Category
    {
        $parentId = $data['parent_id'] ?? null;

        $category = Category::create([
            'parent_id' => $parentId,
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $this->uniqueSlug($data['name']),
            'icon' => $data['icon'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? $this->nextSortOrder($parentId),
        ]);

        CategoryTreeChanged::dispatch();

        return $category;
    }

    private function nextSortOrder(?string $parentId): int
    {
        return 1 + (int) Category::query()->where('parent_id', $parentId)->max('sort_order');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Category::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
