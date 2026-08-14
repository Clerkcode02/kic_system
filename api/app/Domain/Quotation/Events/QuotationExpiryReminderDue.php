<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Events;

use App\Domain\Quotation\Models\Quotation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Not {@see \App\Support\Auditable} — a reminder isn't a critical action
 * per the §13 audit scope, just a pluggable hook for a future notification
 * listener (CLAUDE.md §9 mobile-readiness rule 7).
 */
class QuotationExpiryReminderDue
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Quotation $quotation,
        public readonly int $hoursBeforeExpiry,
    ) {
    }
}
