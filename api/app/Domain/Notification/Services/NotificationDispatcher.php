<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Contracts\ChannelResolvable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Every queued listener in App\Domain\*\Listeners sends through here
 * instead of the Notification facade directly. `Notification::send()`
 * has no channel-restricting parameter at all — for a ShouldQueue
 * notification it just re-queues one SendQueuedNotifications job that
 * sends every via() channel in one foreach, with no isolation between
 * them. `sendNow()` (which this calls) does accept a $channels array, and
 * calling it once per channel is safe here specifically because the
 * caller (every listener in App\Domain\*\Listeners) is itself already a
 * queued listener — this doesn't skip queuing, it replaces one queue hop
 * with N synchronous, individually-guarded sends inside the hop that
 * already happened.
 */
class NotificationDispatcher
{
    /**
     * @param  iterable<object>|object  $notifiables
     */
    public function send(iterable|object $notifiables, Notification&ChannelResolvable $notification): void
    {
        $notifiables = is_iterable($notifiables) ? $notifiables : [$notifiables];

        foreach ($notifiables as $notifiable) {
            $channels = $notification->via($notifiable);

            foreach ($channels as $channel) {
                try {
                    NotificationFacade::sendNow($notifiable, $notification, [$channel]);
                } catch (Throwable $e) {
                    Log::error('Notification channel failed', [
                        'channel' => is_string($channel) ? $channel : $channel::class,
                        'notification' => $notification::class,
                        'notifiable_id' => $notifiable->getKey() ?? null,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
