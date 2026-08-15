<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Services;

use App\Domain\Booking\Models\Booking;
use App\Domain\Quotation\Support\QuotationTotals;
use App\Support\Facades\Settings;
use App\Support\ValueObjects\Money;

/**
 * Single source of truth for a quotation's total (CLAUDE.md §2/§17 — "No
 * trusting client-computed totals... always recomputed server-side"). Every
 * Action that persists a quotation (SendQuotation, ReviseQuotation) must
 * route through this instead of trusting request input for anything beyond
 * labor/materials/fees/discount.
 */
final class QuotationTotalCalculator
{
    private const DEFAULT_PLATFORM_FEE_PERCENTAGE = 10.0;

    private const DEFAULT_TAX_PERCENTAGE = 13.0; // Ontario HST default — Canada-only launch (CLAUDE.md §5).

    public function calculate(
        Booking $booking,
        Money $laborCost,
        Money $materialsCost,
        Money $additionalFees,
        Money $discountAmount,
    ): QuotationTotals {
        $currency = $laborCost->currency;
        $subtotal = $laborCost->add($materialsCost)->add($additionalFees);

        $platformFee = Money::fromMinorUnits(
            (int) round($subtotal->minorUnits * $this->platformFeePercentage($booking) / 100),
            $currency,
        );

        $tax = Money::fromMinorUnits(
            (int) round($subtotal->minorUnits * $this->taxPercentage() / 100),
            $currency,
        );

        $total = $subtotal->add($platformFee)->add($tax)->subtract($discountAmount);

        return new QuotationTotals(
            laborCost: $laborCost,
            materialsCost: $materialsCost,
            additionalFees: $additionalFees,
            platformFee: $platformFee,
            taxAmount: $tax,
            discountAmount: $discountAmount,
            totalAmount: $total,
        );
    }

    /**
     * Category-level override takes precedence over the platform default
     * (CLAUDE.md §12 — "platform fee config: global default + per-category
     * overrides").
     */
    private function platformFeePercentage(Booking $booking): float
    {
        $categoryOverride = $booking->service->category?->platform_fee_percentage;

        if ($categoryOverride !== null) {
            return (float) $categoryOverride;
        }

        return $this->settingValue('platform.default_fee_percentage', self::DEFAULT_PLATFORM_FEE_PERCENTAGE);
    }

    private function taxPercentage(): float
    {
        return $this->settingValue('quotation.tax_percentage', self::DEFAULT_TAX_PERCENTAGE);
    }

    private function settingValue(string $key, float $default): float
    {
        return (float) Settings::get($key, $default);
    }
}
