<?php

declare(strict_types=1);

namespace App\Domain\Payment\Listeners;

use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\Payment\Events\PaymentSucceeded;
use App\Domain\Payment\Notifications\PaymentSucceededNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentSucceededNotification implements ShouldQueue
{
    use ResolvesPayer;

    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(PaymentSucceeded $event): void
    {
        $payer = $this->resolvePayer($event->payment);

        if ($payer === null) {
            return;
        }

        $this->dispatcher->send($payer, new PaymentSucceededNotification($event->payment));
    }
}
