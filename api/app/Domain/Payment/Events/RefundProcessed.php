<?php

declare(strict_types=1);

namespace App\Domain\Payment\Events;

use App\Domain\Payment\Models\Refund;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundProcessed implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Refund $refund)
    {
    }
}
