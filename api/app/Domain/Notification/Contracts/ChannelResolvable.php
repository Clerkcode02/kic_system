<?php

declare(strict_types=1);

namespace App\Domain\Notification\Contracts;

/**
 * Implemented (structurally, via the ResolvesNotificationChannels trait) by
 * every notification class in App\Domain\*\Notifications. Illuminate's base
 * Notification class deliberately doesn't declare via() — each subclass
 * defines it by convention — so NotificationDispatcher needs this to call
 * via() against a known type instead of the untyped base class.
 */
interface ChannelResolvable
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array;
}
