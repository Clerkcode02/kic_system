<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Support\Action;
use Illuminate\Support\Facades\DB;

/**
 * Reacts to `payment_intent.payment_failed`. Leaves the booking/milestone
 * status untouched — the payer retries via POST /payments/intents, which
 * recomputes the still-unpaid balance from scratch.
 */
final class MarkPaymentFailed implements Action
{
    public function handle(string $stripePaymentIntentId): void
    {
        DB::transaction(function () use ($stripePaymentIntentId) {
            $payment = Payment::query()
                ->lockForUpdate()
                ->where('stripe_payment_intent_id', $stripePaymentIntentId)
                ->first();

            if ($payment === null || $payment->status === PaymentStatus::Failed) {
                return;
            }

            $payment->update(['status' => PaymentStatus::Failed]);

            AuditLog::create([
                'actor_id' => null,
                'action' => 'payment.failed',
                'auditable_type' => 'payment',
                'auditable_id' => $payment->id,
                'before_state' => ['status' => PaymentStatus::Pending->value],
                'after_state' => ['status' => PaymentStatus::Failed->value],
                'ip_address' => null,
                'user_agent' => null,
            ]);
        });
    }
}
