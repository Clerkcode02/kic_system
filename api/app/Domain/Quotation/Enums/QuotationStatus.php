<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Enums;

enum QuotationStatus: string
{
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
    case Expired = 'expired';
}
