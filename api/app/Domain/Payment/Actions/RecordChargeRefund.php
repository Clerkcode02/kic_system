<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Booking\Enums\BookingPaymentStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\RefundStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Refund;
use App\Support\Action;
use Illuminate\Support\Facades\DB;

/**
 * Reacts to `charge.refunded` — confirmation that a refund (whether we
 * initiated it via IssueRefund, or it happened directly on the Stripe
 * dashboard) actually settled. Reconciles a matching pending Refund row if
 * one exists; otherwise records the fact for the ledger regardless.
 */
final class RecordChargeRefund implements Action
{
    public function handle(string $stripePaymentIntentId): void
    {
        DB::transaction(function () use ($stripePaymentIntentId) {
            $payment = Payment::query()
                ->lockForUpdate()
                ->where('stripe_payment_intent_id', $stripePaymentIntentId)
                ->first();

            if ($payment === null || $payment->status === PaymentStatus::Refunded) {
                return;
            }

            $payment->update(['status' => PaymentStatus::Refunded]);

            $pendingRefund = Refund::query()
                ->where('payment_id', $payment->id)
                ->where('status', RefundStatus::Pending)
                ->first();

            if ($pendingRefund !== null) {
                $pendingRefund->update(['status' => RefundStatus::Succeeded]);
            }

            AuditLog::create([
                'actor_id' => null,
                'action' => 'payment.refund_confirmed',
                'auditable_type' => 'payment',
                'auditable_id' => $payment->id,
                'before_state' => ['status' => PaymentStatus::Succeeded->value],
                'after_state' => ['status' => PaymentStatus::Refunded->value],
                'ip_address' => null,
                'user_agent' => null,
            ]);

            if ($payment->payable_type === 'booking') {
                $booking = Booking::query()->find($payment->payable_id);

                if ($booking !== null) {
                    $booking->update(['payment_status' => BookingPaymentStatus::Refunded]);
                }
            }
        });
    }
}
