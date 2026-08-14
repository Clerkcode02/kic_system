<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Booking\Actions\TransitionBookingStatus;
use App\Domain\Booking\Enums\BookingPaymentStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Events\PaymentSucceeded;
use App\Domain\Payment\Models\Payment;
use App\Support\Action;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Reacts to `payment_intent.succeeded`. SRS §7/§8: the booking only moves
 * Accepted -> Scheduled once a payment actually clears — never optimistically
 * from the client (CLAUDE.md §6 "never mark paid from the client"). Idempotent
 * on the intent id: replays (same or a different Stripe event id) that land
 * on an already-succeeded Payment are a no-op.
 */
final class CapturePayment implements Action
{
    public function __construct(private readonly TransitionBookingStatus $transition)
    {
    }

    public function handle(string $stripePaymentIntentId): void
    {
        DB::transaction(function () use ($stripePaymentIntentId) {
            $payment = Payment::query()
                ->lockForUpdate()
                ->where('stripe_payment_intent_id', $stripePaymentIntentId)
                ->first();

            if ($payment === null || $payment->status === PaymentStatus::Succeeded) {
                return;
            }

            $payment->update(['status' => PaymentStatus::Succeeded]);

            PaymentSucceeded::dispatch($payment);

            if ($payment->payable_type !== 'booking') {
                return;
            }

            $booking = Booking::query()->lockForUpdate()->find($payment->payable_id);

            if ($booking === null) {
                return;
            }

            $booking->update(['payment_status' => $this->paymentStatus($booking)]);

            if ($booking->status === BookingStatus::Accepted) {
                $this->transition->handle($booking, BookingStatus::Scheduled, null, 'Payment confirmed via Stripe webhook.');
            }
        });
    }

    private function paymentStatus(Booking $booking): BookingPaymentStatus
    {
        $quotation = $booking->quotations()->where('status', 'accepted')->latest()->first();

        if ($quotation === null) {
            return BookingPaymentStatus::Paid;
        }

        $succeeded = Payment::query()
            ->where('payable_type', 'booking')
            ->where('payable_id', $booking->id)
            ->where('status', PaymentStatus::Succeeded)
            ->get()
            ->reduce(fn (?Money $carry, Payment $p) => $carry === null ? $p->amount : $carry->add($p->amount), null);

        if ($succeeded === null) {
            return BookingPaymentStatus::Unpaid;
        }

        return $succeeded->minorUnits >= $quotation->total_amount->minorUnits
            ? BookingPaymentStatus::Paid
            : BookingPaymentStatus::Partial;
    }
}
