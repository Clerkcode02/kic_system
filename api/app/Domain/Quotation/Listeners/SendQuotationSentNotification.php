<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Listeners;

use App\Domain\Notification\Services\BookingNotifiableResolver;
use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\Quotation\Events\QuotationSent;
use App\Domain\Quotation\Notifications\QuotationSentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendQuotationSentNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly BookingNotifiableResolver $notifiables,
    ) {
    }

    public function handle(QuotationSent $event): void
    {
        $booking = $event->quotation->booking;

        // SRS §6.1: a guest customer has no users row — the resolver
        // hands back a mail-only on-demand notifiable instead, so this
        // listener needs no actor-kind branch of its own.
        $customer = $booking === null ? null : $this->notifiables->forCustomer($booking);

        if ($customer === null) {
            return;
        }

        $this->dispatcher->send($customer, new QuotationSentNotification($event->quotation));
    }
}
