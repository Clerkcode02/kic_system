<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Listeners;

use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\Quotation\Events\QuotationRejected;
use App\Domain\Quotation\Notifications\QuotationRejectedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendQuotationRejectedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(QuotationRejected $event): void
    {
        $provider = $event->quotation->booking?->provider?->user;

        if ($provider === null) {
            return;
        }

        $this->dispatcher->send($provider, new QuotationRejectedNotification($event->quotation));
    }
}
