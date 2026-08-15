<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Actions;

use App\Domain\Booking\Actions\TransitionBookingStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentGateway;
use App\Domain\Quotation\Events\QuotationAccepted;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\Quotation\StateMachines\QuotationStateMachine;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\PaymentsBlockedException;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * SRS §8: "WaitingForCustomer --> Accepted: Customer accepts + pays". The
 * actual charge confirmation is a webhook concern (Accepted --> Scheduled
 * "payment confirmed") — this Action only creates the pending Payment row
 * and the destination-charge payment intent behind {@see PaymentGateway}.
 *
 * If the quotation carries a `deposit_percentage`, only that portion is
 * charged now (type=deposit); the remainder is captured on completion
 * (CLAUDE.md §5 "Deposits").
 */
final class AcceptQuotation implements Action
{
    public function __construct(
        private readonly TransitionBookingStatus $transition,
        private readonly PaymentGateway $paymentGateway,
    ) {
    }

    /**
     * @return array{quotation: Quotation, payment: Payment, client_secret: ?string}
     */
    public function handle(Quotation $quotation, User $actor): array
    {
        if (! $quotation->booking->provider->canReceiveFunds()) {
            throw new PaymentsBlockedException(
                'This provider cannot receive payouts yet — Stripe Connect onboarding is incomplete.',
                'provider_payouts_not_enabled',
            );
        }

        return DB::transaction(function () use ($quotation, $actor) {
            $machine = new QuotationStateMachine($quotation->status);
            $machine->transition('accepted');
            $quotation->update(['status' => 'accepted']);

            $booking = $this->transition->handle(
                $quotation->booking,
                BookingStatus::Accepted,
                $actor,
                'Customer accepted the quotation.',
            );

            $depositPercentage = $quotation->deposit_percentage !== null ? (float) $quotation->deposit_percentage : null;
            $isDeposit = $depositPercentage !== null && $depositPercentage > 0.0;

            $amount = $isDeposit
                ? $this->proportion($quotation->total_amount, $depositPercentage)
                : $quotation->total_amount;

            $platformFeeAmount = $isDeposit
                ? $this->proportion($quotation->platform_fee, $depositPercentage)
                : $quotation->platform_fee;

            $intent = $this->paymentGateway->createBookingPaymentIntent(
                $amount,
                $platformFeeAmount,
                $booking->provider->stripe_connect_account_id,
                ['booking_id' => $booking->id, 'quotation_id' => $quotation->id],
            );

            $payment = Payment::create([
                'payable_type' => 'booking',
                'payable_id' => $booking->id,
                'stripe_payment_intent_id' => $intent->intentId,
                'amount' => $amount,
                'platform_fee_amount' => $platformFeeAmount,
                'provider_net_amount' => $amount->subtract($platformFeeAmount),
                'currency' => $quotation->currency,
                'type' => $isDeposit ? PaymentType::Deposit : PaymentType::Full,
                'status' => PaymentStatus::Pending,
            ]);

            QuotationAccepted::dispatch($quotation->fresh(), $payment, $actor);

            return [
                'quotation' => $quotation->fresh(['lineItems', 'booking']),
                'payment' => $payment,
                'client_secret' => $intent->clientSecret,
            ];
        });
    }

    private function proportion(Money $amount, float $percentage): Money
    {
        return Money::fromMinorUnits(
            (int) round($amount->minorUnits * $percentage / 100),
            $amount->currency,
        );
    }
}
