<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Listeners;

use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\Quotation\Events\QuotationSent;
use App\Domain\Quotation\Notifications\QuotationSentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendQuotationSentNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function handle(QuotationSent $event): void
    {
        $customer = $event->quotation->booking?->customer;

        if ($customer === null) {
            return;
        }

        $this->dispatcher->send($customer, new QuotationSentNotification($event->quotation));
    }
}
