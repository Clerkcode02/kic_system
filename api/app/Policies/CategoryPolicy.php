<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Catalog\Models\Category;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;

/**
 * Categories are platform-global — no ownership dimension, so every
 * ability collapses to the same admin-only permission check.
 */
class CategoryPolicy
{
    public function create(User $user): bool
    {
        return $user->can(PermissionName::CategoriesManage->value);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can(PermissionName::CategoriesManage->value);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can(PermissionName::CategoriesManage->value);
    }

    public function reorder(User $user): bool
    {
        return $user->can(PermissionName::CategoriesManage->value);
    }
}
