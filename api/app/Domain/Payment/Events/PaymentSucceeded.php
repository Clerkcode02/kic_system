<?php

declare(strict_types=1);

namespace App\Domain\Payment\Events;

use App\Domain\Payment\Models\Payment;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentSucceeded implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Payment $payment)
    {
    }
}
