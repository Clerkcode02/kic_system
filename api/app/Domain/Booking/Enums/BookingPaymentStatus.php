<?php

declare(strict_types=1);

namespace App\Domain\Booking\Enums;

enum BookingPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Refunded = 'refunded';
}
