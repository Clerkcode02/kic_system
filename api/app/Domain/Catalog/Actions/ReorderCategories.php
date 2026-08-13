<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Events\CategoryTreeChanged;
use App\Domain\Catalog\Models\Category;
use Illuminate\Support\Facades\DB;

class ReorderCategories
{
    /**
     * @param  list<array{id: string, sort_order: int}>  $items
     */
    public function handle(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                Category::query()->whereKey($item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        });

        CategoryTreeChanged::dispatch();
    }
}
