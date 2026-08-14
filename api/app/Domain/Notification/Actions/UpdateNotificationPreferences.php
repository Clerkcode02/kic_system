<?php

declare(strict_types=1);

namespace App\Domain\Notification\Actions;

use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\User\Models\User;
use App\Support\Action;
use Illuminate\Database\Eloquent\Collection;

/**
 * In-app (the 'database' channel) is always on and never persisted as a
 * togglable preference — only email/push_web are user-controlled
 * (notifications prompt item 1).
 */
final class UpdateNotificationPreferences implements Action
{
    /**
     * @param  array<int, array{category: string, email?: bool, push_web?: bool}>  $preferences
     * @return Collection<int, NotificationPreference>
     */
    public function handle(User $user, array $preferences): Collection
    {
        foreach ($preferences as $preference) {
            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'category' => $preference['category']],
                array_intersect_key($preference, array_flip(['email', 'push_web'])),
            );
        }

        return $user->notificationPreferences()->get();
    }
}
