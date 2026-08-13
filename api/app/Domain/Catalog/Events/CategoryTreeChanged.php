<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by every category write (create/update/delete/reorder) — the only
 * side effect any of them needs is invalidating the cached tree.
 */
class CategoryTreeChanged
{
    use Dispatchable;
}
