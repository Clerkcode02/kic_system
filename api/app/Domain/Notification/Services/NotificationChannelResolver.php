<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Channels\WebPushChannel;
use App\Domain\Notification\Enums\NotificationCategory;
use App\Domain\User\Models\User;

/**
 * Resolves the channel list for a single notifiable + category from
 * `notification_preferences`. 'database' (in-app) is always included and
 * cannot be turned off (CLAUDE.md notification prompt item 1). A missing
 * preference row defaults open (matches the migration's column defaults),
 * so a user who has never touched their settings still gets mail/push.
 *
 * Adding a mobile push channel later is one class (MobilePushChannel) plus
 * one branch here reading `push_mobile` — no Notification class changes.
 */
class NotificationChannelResolver
{
    /**
     * @return list<string>
     */
    public function resolve(object $notifiable, NotificationCategory $category): array
    {
        $channels = ['database'];

        if (! $notifiable instanceof User) {
            return $channels;
        }

        $preference = $notifiable->notificationPreferences()
            ->where('category', $category->value)
            ->first();

        if ($preference === null || $preference->email) {
            $channels[] = 'mail';
        }

        if ($preference === null || $preference->push_web) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }
}
