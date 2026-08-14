<?php

declare(strict_types=1);

namespace App\Domain\Notification\Concerns;

use App\Domain\Notification\Enums\NotificationCategory;
use App\Domain\Notification\Services\NotificationChannelResolver;

/**
 * Every notification class in App\Domain\*\Notifications uses this trait
 * instead of hand-rolling via() — it keeps the per-user, per-category
 * preference lookup (and the "database is always on" rule) in one place.
 */
trait ResolvesNotificationChannels
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return app(NotificationChannelResolver::class)->resolve($notifiable, $this->notificationCategory());
    }

    abstract protected function notificationCategory(): NotificationCategory;
}
