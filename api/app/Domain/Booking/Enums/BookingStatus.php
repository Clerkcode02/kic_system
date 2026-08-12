<?php

declare(strict_types=1);

namespace App\Domain\Booking\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case WaitingForQuotation = 'waiting_for_quotation';
    case QuotationSent = 'quotation_sent';
    case WaitingForCustomer = 'waiting_for_customer';
    case Accepted = 'accepted';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Declined = 'declined';
    case QuotationExpired = 'quotation_expired';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
