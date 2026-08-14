<?php

declare(strict_types=1);

namespace App\Domain\Payment\Listeners;

use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\Payment\Events\RefundProcessed;
use App\Domain\Payment\Notifications\RefundProcessedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendRefundProcessedNotification implements ShouldQueue
{
    use ResolvesPayer;

    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(RefundProcessed $event): void
    {
        $payment = $event->refund->payment;

        if ($payment === null) {
            return;
        }

        $payer = $this->resolvePayer($payment);

        if ($payer === null) {
            return;
        }

        $this->dispatcher->send($payer, new RefundProcessedNotification($event->refund));
    }
}
