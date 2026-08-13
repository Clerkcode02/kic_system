<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Review\Models\Review;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;

class ReviewPolicy
{
    public function view(User $user, Review $review): bool
    {
        return $user->can(PermissionName::ReviewsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ReviewsCreate->value);
    }

    public function reply(User $user, Review $review): bool
    {
        return $user->can(PermissionName::ReviewsReply->value) && $review->reviewee_id === $user->id;
    }
}
