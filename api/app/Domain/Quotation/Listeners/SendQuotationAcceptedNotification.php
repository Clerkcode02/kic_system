<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Listeners;

use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\Quotation\Events\QuotationAccepted;
use App\Domain\Quotation\Notifications\QuotationAcceptedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendQuotationAcceptedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(QuotationAccepted $event): void
    {
        $provider = $event->quotation->booking?->provider?->user;

        if ($provider === null) {
            return;
        }

        $this->dispatcher->send($provider, new QuotationAcceptedNotification($event->quotation));
    }
}
