<?php

declare(strict_types=1);

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentGateway;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use App\Support\ValueObjects\BookingActor;
use App\Support\ValueObjects\Money;

/**
 * The customer half of the compound `InProgress --> Completed` edge (SRS
 * §8). Requires MarkProviderComplete to have already run; the actual
 * enum transition (and its illegal-transition/409 guard) is delegated to
 * TransitionBookingStatus so this stays the one call site that flips a
 * booking to Completed.
 *
 * If the accepted quotation was a deposit, the remainder is captured here
 * (CLAUDE.md §5 "Deposits" — "the remainder is captured on completion").
 */
final class ConfirmBookingCompletion implements Action
{
    public function __construct(
        private readonly TransitionBookingStatus $transition,
        private readonly PaymentGateway $paymentGateway,
    ) {
    }

    public function handle(Booking $booking, User $customer): Booking
    {
        if ($booking->provider_completed_at === null) {
            throw new ConflictException(
                'The provider has not yet marked this booking complete.',
                'provider_has_not_marked_complete',
            );
        }

        $booking = $this->transition->handle($booking, BookingStatus::Completed, BookingActor::user($customer), 'Customer confirmed completion.');

        $this->captureRemainderIfDeposited($booking);

        return $booking;
    }

    private function captureRemainderIfDeposited(Booking $booking): void
    {
        $quotation = $booking->quotations()->where('status', 'accepted')->latest()->first();

        if ($quotation === null || $quotation->deposit_percentage === null) {
            return;
        }

        $paidSoFar = Payment::query()
            ->where('payable_type', 'booking')
            ->where('payable_id', $booking->id)
            ->where('status', '!=', PaymentStatus::Failed)
            ->get()
            ->reduce(fn (?Money $carry, Payment $payment) => $carry === null ? $payment->amount : $carry->add($payment->amount), null);

        $remaining = $paidSoFar === null ? $quotation->total_amount : $quotation->total_amount->subtract($paidSoFar);

        if ($remaining->isZero()) {
            return;
        }

        // The full platform fee was already withheld on the deposit leg —
        // application_fee_amount on the remainder is zero.
        $noFee = $remaining->subtract($remaining);

        $intent = $this->paymentGateway->createBookingPaymentIntent(
            $remaining,
            $noFee,
            $booking->provider->stripe_connect_account_id,
            ['booking_id' => $booking->id, 'quotation_id' => $quotation->id, 'leg' => 'remainder'],
        );

        Payment::create([
            'payable_type' => 'booking',
            'payable_id' => $booking->id,
            'stripe_payment_intent_id' => $intent->intentId,
            'amount' => $remaining,
            'platform_fee_amount' => $noFee,
            'provider_net_amount' => $remaining,
            'currency' => $quotation->currency,
            'type' => PaymentType::Partial,
            'status' => PaymentStatus::Pending,
        ]);
    }
}
