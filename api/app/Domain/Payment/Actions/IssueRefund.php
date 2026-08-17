<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Booking\Actions\TransitionBookingStatus;
use App\Domain\Booking\Enums\BookingPaymentStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\RefundStatus;
use App\Domain\Payment\Events\RefundProcessed;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Refund;
use App\Domain\Payment\Services\PaymentGateway;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use App\Support\Facades\Settings;
use App\Support\PaymentsBlockedException;
use App\Support\ValueObjects\BookingActor;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SRS §7: "Refunds ... always admin-authorized above a configurable
 * threshold." The admin endpoint (payments.refund) covers the base
 * authorization; this Action adds the amount-sensitive gate — a refund
 * above `refund.large_amount_threshold` additionally requires
 * `payments.refund-large`, held only by super_admin by default.
 */
final class IssueRefund implements Action
{
    private const DEFAULT_LARGE_REFUND_THRESHOLD = '500.00';

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly TransitionBookingStatus $bookingTransition,
    ) {
    }

    public function handle(Payment $payment, User $actor, ?Money $amount, ?string $reason): Refund
    {
        return DB::transaction(function () use ($payment, $actor, $amount, $reason) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Succeeded) {
                throw new ConflictException('Only a succeeded payment can be refunded.', 'payment_not_refundable');
            }

            $refundAmount = $amount ?? $payment->amount;

            if ($refundAmount->minorUnits <= 0 || $refundAmount->minorUnits > $payment->amount->minorUnits) {
                throw new ConflictException(
                    'Refund amount must be positive and not exceed the original payment.',
                    'invalid_refund_amount',
                );
            }

            $this->assertAuthorizedForAmount($actor, $refundAmount);

            $refund = Refund::create([
                'payment_id' => $payment->id,
                'amount' => $refundAmount,
                'currency' => $payment->currency,
                'reason' => $reason,
                'stripe_refund_id' => null,
                'status' => RefundStatus::Pending,
                'initiated_by' => $actor->id,
            ]);

            $result = $this->gateway->refund(
                (string) $payment->stripe_payment_intent_id,
                $refundAmount,
                'refund-'.Str::uuid(),
            );

            $refund->update(['stripe_refund_id' => $result->refundId, 'status' => RefundStatus::Succeeded]);
            $payment->update(['status' => PaymentStatus::Refunded]);

            $this->reflectOnBooking($payment, $actor, $reason);

            RefundProcessed::dispatch($refund, $actor);

            return $refund->fresh();
        });
    }

    private function reflectOnBooking(Payment $payment, User $actor, ?string $reason): void
    {
        if ($payment->payable_type !== 'booking') {
            return;
        }

        $booking = Booking::query()->lockForUpdate()->find($payment->payable_id);

        if ($booking === null) {
            return;
        }

        $booking->update(['payment_status' => BookingPaymentStatus::Refunded]);

        // The state machine only allows Completed -> Refunded — a booking
        // refunded pre-completion (e.g. a post-acceptance cancellation)
        // keeps its current status; payment_status above is the source of
        // truth for "this booking's money came back" in that case.
        if ($booking->status === BookingStatus::Completed) {
            $this->bookingTransition->handle($booking, BookingStatus::Refunded, BookingActor::user($actor), $reason ?? 'Refund issued by admin.');
        }
    }

    private function assertAuthorizedForAmount(User $actor, Money $amount): void
    {
        $threshold = $this->thresholdAmount($amount->currency);

        if ($amount->minorUnits > $threshold->minorUnits && ! $actor->can(PermissionName::PaymentsRefundLarge->value)) {
            throw new PaymentsBlockedException(
                'Refunds above the configurable threshold require elevated admin authorization.',
                'refund_requires_elevated_admin',
            );
        }
    }

    private function thresholdAmount(string $currency): Money
    {
        $value = (float) Settings::get('refund.large_amount_threshold', (float) self::DEFAULT_LARGE_REFUND_THRESHOLD);

        return Money::fromDecimal(number_format($value, 2, '.', ''), $currency);
    }
}
