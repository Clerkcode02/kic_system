<?php

declare(strict_types=1);

namespace App\Domain\Notification\Channels;

use App\Domain\Notification\Models\WebPushSubscription;
use App\Domain\User\Models\User;
use Illuminate\Notifications\Notification;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Browser Push API delivery via VAPID (CLAUDE.md notification prompt item
 * 3 — web push now, MobilePushChannel/FCM is a later, additive class). A
 * notification class opts in by defining toWebPush(); classes that don't
 * are silently skipped by ResolvesNotificationChannels never routing here
 * (see NotificationChannelResolver — only added when push_web is on).
 */
class WebPushChannel
{
    public function __construct(private readonly WebPush $webPush)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush') || ! $notifiable instanceof User) {
            return;
        }

        $subscriptions = WebPushSubscription::query()->where('user_id', $notifiable->id)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode($notification->toWebPush($notifiable), JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $subscription) {
            $this->webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]),
                $payload,
            );
        }

        foreach ($this->webPush->flush() as $report) {
            if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                WebPushSubscription::query()
                    ->where('user_id', $notifiable->id)
                    ->where('endpoint', $report->getRequest()->getUri())
                    ->delete();
            }
        }
    }
}
