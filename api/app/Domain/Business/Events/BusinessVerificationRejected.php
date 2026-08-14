<?php

declare(strict_types=1);

namespace App\Domain\Business\Events;

use App\Domain\Business\Models\Business;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BusinessVerificationRejected implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Business $business,
        public readonly ?string $reason,
    ) {
    }
}
