<?php

declare(strict_types=1);

namespace App\Domain\Notification\Queries;

use App\Domain\Notification\Enums\NotificationCategory;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\User\Models\User;
use Illuminate\Support\Collection;

/**
 * Every category always appears in the response, even for a user who has
 * never touched their settings — a missing row is filled with the
 * migration's open-by-default values rather than being omitted, so the SPA
 * never has to special-case "no preference saved yet".
 */
final class ListNotificationPreferencesQuery
{
    /**
     * @return Collection<int, NotificationPreference>
     */
    public function handle(User $user): Collection
    {
        $existing = $user->notificationPreferences()->get()->keyBy('category');

        return collect(NotificationCategory::cases())->map(
            fn (NotificationCategory $category) => $existing->get($category->value) ?? new NotificationPreference([
                'user_id' => $user->id,
                'category' => $category->value,
                'email' => true,
                'push_web' => true,
            ])
        )->values();
    }
}
