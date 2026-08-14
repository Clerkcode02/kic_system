<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Events;

use App\Domain\Payment\Models\Payment;
use App\Domain\Quotation\Models\Quotation;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotationAccepted implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Quotation $quotation,
        public readonly Payment $payment,
    ) {
    }
}
