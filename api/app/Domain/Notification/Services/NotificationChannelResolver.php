<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Channels\WebPushChannel;
use App\Domain\Notification\Enums\NotificationCategory;
use App\Domain\User\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;

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
        // SRS §6.1: a guest has no users row and therefore no in-app inbox,
        // no preference row and no push subscription — mail is the only
        // channel that can reach them, and 'database' would try to write a
        // notification against a null notifiable id.
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

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
