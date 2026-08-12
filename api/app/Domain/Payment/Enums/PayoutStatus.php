<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum PayoutStatus: string
{
    case Scheduled = 'scheduled';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
}
