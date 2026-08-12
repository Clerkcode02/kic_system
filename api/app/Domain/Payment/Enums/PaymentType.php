<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum PaymentType: string
{
    case Full = 'full';
    case Deposit = 'deposit';
    case Partial = 'partial';
    case Escrow = 'escrow';
}
