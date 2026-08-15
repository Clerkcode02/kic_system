<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Actions;

use App\Domain\Booking\Actions\TransitionBookingStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Quotation\Events\QuotationSent;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\Quotation\Models\QuotationLineItem;
use App\Domain\Quotation\Services\QuotationTotalCalculator;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\Facades\Settings;
use App\Support\PaymentsBlockedException;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * SRS §9: a provider's first priced response to a booking. The booking
 * must currently be WaitingForQuotation — TransitionBookingStatus enforces
 * that via BookingStateMachine and throws IllegalStateTransitionException
 * (409) otherwise, which is also what makes "quoting a booking that isn't
 * in WaitingForQuotation returns 409" true without a bespoke check here.
 *
 * Totals are always recomputed server-side (CLAUDE.md §2/§17) — any
 * `total_amount`/`platform_fee`/`tax_amount` a client sends is ignored.
 */
final class SendQuotation implements Action
{
    private const DEFAULT_VALIDITY_HOURS = 120; // 5 days — configurable via platform_settings.

    public function __construct(
        private readonly QuotationTotalCalculator $calculator,
        private readonly TransitionBookingStatus $transition,
    ) {
    }

    /**
     * @param  array{
     *     labor_cost: string|float,
     *     materials_cost: string|float,
     *     additional_fees: string|float,
     *     discount_amount?: string|float,
     *     line_items?: list<array{description: string, quantity: string|float, unit_price: string|float}>,
     * }  $data
     */
    public function handle(Booking $booking, User $actor, array $data): Quotation
    {
        if (! $booking->provider->canReceiveFunds()) {
            throw new PaymentsBlockedException(
                'This provider cannot receive payouts yet — Stripe Connect onboarding is incomplete.',
                'provider_payouts_not_enabled',
            );
        }

        return DB::transaction(function () use ($booking, $actor, $data) {
            $currency = 'CAD';

            $totals = $this->calculator->calculate(
                booking: $booking,
                laborCost: Money::fromDecimal((string) $data['labor_cost'], $currency),
                materialsCost: Money::fromDecimal((string) $data['materials_cost'], $currency),
                additionalFees: Money::fromDecimal((string) $data['additional_fees'], $currency),
                discountAmount: Money::fromDecimal((string) ($data['discount_amount'] ?? '0'), $currency),
            );

            $quotation = Quotation::create([
                'booking_id' => $booking->id,
                'labor_cost' => $totals->laborCost,
                'materials_cost' => $totals->materialsCost,
                'additional_fees' => $totals->additionalFees,
                'platform_fee' => $totals->platformFee,
                'tax_amount' => $totals->taxAmount,
                'discount_amount' => $totals->discountAmount,
                'total_amount' => $totals->totalAmount,
                'currency' => $currency,
                'valid_until' => now()->addHours($this->validityHours()),
                'revision_number' => 1,
                'status' => 'sent',
            ]);

            $this->createLineItems($quotation, $data['line_items'] ?? [], $currency);

            // §8 diagram: WaitingForQuotation --(provider sends)--> QuotationSent
            // --(notify customer, automatic)--> WaitingForCustomer.
            $booking = $this->transition->handle($booking, BookingStatus::QuotationSent, $actor, 'Provider sent a quotation.');
            $booking = $this->transition->handle($booking, BookingStatus::WaitingForCustomer, $actor, 'Awaiting customer response.');

            QuotationSent::dispatch($quotation, $actor);

            return $quotation->fresh(['lineItems', 'booking']);
        });
    }

    /**
     * @param  list<array{description: string, quantity: string|float, unit_price: string|float}>  $lineItems
     */
    private function createLineItems(Quotation $quotation, array $lineItems, string $currency): void
    {
        foreach ($lineItems as $index => $item) {
            $unitPrice = Money::fromDecimal((string) $item['unit_price'], $currency);
            $quantity = (float) $item['quantity'];

            QuotationLineItem::create([
                'quotation_id' => $quotation->id,
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => Money::fromMinorUnits((int) round($unitPrice->minorUnits * $quantity), $currency),
                'currency' => $currency,
                'sort_order' => $index,
            ]);
        }
    }

    private function validityHours(): int
    {
        return (int) Settings::get('quotation.default_validity_hours', self::DEFAULT_VALIDITY_HOURS);
    }
}
