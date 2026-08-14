<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Support;

use App\Support\ValueObjects\Money;

/**
 * Server-computed quotation totals (CLAUDE.md §2 — "No trusting
 * client-computed totals"). Whatever `total_amount` a client sends is
 * discarded; this is the only source of the persisted total.
 */
final class QuotationTotals
{
    public function __construct(
        public readonly Money $laborCost,
        public readonly Money $materialsCost,
        public readonly Money $additionalFees,
        public readonly Money $platformFee,
        public readonly Money $taxAmount,
        public readonly Money $discountAmount,
        public readonly Money $totalAmount,
    ) {
    }
}
