<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Listeners;

use App\Domain\Notification\Services\BookingNotifiableResolver;
use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\Quotation\Events\QuotationExpiryReminderDue;
use App\Domain\Quotation\Notifications\QuotationExpiringNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendQuotationExpiringNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly BookingNotifiableResolver $notifiables,
    ) {
    }

    public function handle(QuotationExpiryReminderDue $event): void
    {
        $booking = $event->quotation->booking;

        // Guest customers get the same reminder by mail (SRS §6.1).
        $customer = $booking === null ? null : $this->notifiables->forCustomer($booking);

        if ($customer === null) {
            return;
        }

        $this->dispatcher->send(
            $customer,
            new QuotationExpiringNotification($event->quotation, $event->hoursBeforeExpiry),
        );
    }
}
