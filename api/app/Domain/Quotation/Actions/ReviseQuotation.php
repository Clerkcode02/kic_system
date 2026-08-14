<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Actions;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Booking\Actions\TransitionBookingStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\Quotation\Events\QuotationRevised;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\Quotation\Models\QuotationLineItem;
use App\Domain\Quotation\Services\QuotationTotalCalculator;
use App\Domain\Quotation\StateMachines\QuotationStateMachine;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\PaymentsBlockedException;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * SRS §9: "Sent --> Superseded: Provider/customer requests revision -> new
 * quotation created" and "Rejected --> Sent: Provider resends revised quote
 * (new record, revision_number+1)". A revision is never an in-place edit —
 * this always creates a new Quotation row and marks whichever row it
 * replaces (Sent or Rejected) as Superseded, per QuotationStateMachine.
 */
final class ReviseQuotation implements Action
{
    private const DEFAULT_VALIDITY_HOURS = 120;

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
    public function handle(Quotation $previous, User $actor, array $data): Quotation
    {
        if (! $previous->booking->provider->canReceiveFunds()) {
            throw new PaymentsBlockedException(
                'This provider cannot receive payouts yet — Stripe Connect onboarding is incomplete.',
                'provider_payouts_not_enabled',
            );
        }

        return DB::transaction(function () use ($previous, $actor, $data) {
            $machine = new QuotationStateMachine($previous->status);
            $machine->transition('superseded');
            $previous->update(['status' => 'superseded']);

            $booking = $previous->booking()->lockForUpdate()->first();

            // Rejection already routes the booking back to WaitingForQuotation;
            // revising a still-Sent quote (before the customer decided) needs
            // that hop done here first — only legal from WaitingForCustomer.
            if ($booking->status !== BookingStatus::WaitingForQuotation) {
                $booking = $this->transition->handle($booking, BookingStatus::WaitingForQuotation, $actor, 'Provider is revising the quotation.');
            }

            $currency = $previous->currency;

            $totals = $this->calculator->calculate(
                booking: $booking,
                laborCost: Money::fromDecimal((string) $data['labor_cost'], $currency),
                materialsCost: Money::fromDecimal((string) $data['materials_cost'], $currency),
                additionalFees: Money::fromDecimal((string) $data['additional_fees'], $currency),
                discountAmount: Money::fromDecimal((string) ($data['discount_amount'] ?? '0'), $currency),
            );

            $quotation = Quotation::create([
                'booking_id' => $booking->id,
                'previous_quotation_id' => $previous->id,
                'labor_cost' => $totals->laborCost,
                'materials_cost' => $totals->materialsCost,
                'additional_fees' => $totals->additionalFees,
                'platform_fee' => $totals->platformFee,
                'tax_amount' => $totals->taxAmount,
                'discount_amount' => $totals->discountAmount,
                'total_amount' => $totals->totalAmount,
                'currency' => $currency,
                'valid_until' => now()->addHours($this->validityHours()),
                'revision_number' => $previous->revision_number + 1,
                'status' => 'sent',
            ]);

            foreach (($data['line_items'] ?? []) as $index => $item) {
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

            $booking = $this->transition->handle($booking, BookingStatus::QuotationSent, $actor, 'Provider sent a revised quotation.');
            $this->transition->handle($booking, BookingStatus::WaitingForCustomer, $actor, 'Awaiting customer response.');

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'quotation.revised',
                'auditable_type' => 'quotation',
                'auditable_id' => $quotation->id,
                'before_state' => ['previous_quotation_id' => $previous->id, 'revision_number' => $previous->revision_number],
                'after_state' => ['revision_number' => $quotation->revision_number, 'total_amount' => $quotation->total_amount->toDecimal()],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            QuotationRevised::dispatch($quotation, $previous->fresh());

            return $quotation->fresh(['lineItems', 'booking']);
        });
    }

    private function validityHours(): int
    {
        $setting = PlatformSetting::query()->where('key', 'quotation.default_validity_hours')->first();

        return $setting !== null ? (int) $setting->typedValue : self::DEFAULT_VALIDITY_HOURS;
    }
}
