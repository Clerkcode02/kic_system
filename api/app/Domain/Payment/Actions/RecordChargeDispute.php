<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Booking\Models\Booking;
use App\Domain\Dispute\Enums\DisputeStatus;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Support\Action;
use Illuminate\Support\Facades\DB;

/**
 * Reacts to `charge.dispute.created`. A Stripe chargeback and the
 * platform's own dispute-resolution flow (customer vs. provider
 * disagreements) share one `disputes` table rather than becoming two
 * disconnected systems — this maps Stripe's event onto a new row in it.
 */
final class RecordChargeDispute implements Action
{
    public function handle(string $stripePaymentIntentId, string $reason): void
    {
        DB::transaction(function () use ($stripePaymentIntentId, $reason) {
            $payment = Payment::query()
                ->lockForUpdate()
                ->where('stripe_payment_intent_id', $stripePaymentIntentId)
                ->first();

            if ($payment === null || $payment->status === PaymentStatus::Disputed) {
                return;
            }

            $payment->update(['status' => PaymentStatus::Disputed]);

            $raisedBy = $this->payerIdFor($payment);

            if ($raisedBy === null) {
                return;
            }

            $alreadyOpen = Dispute::query()
                ->where('disputable_type', $payment->payable_type)
                ->where('disputable_id', $payment->payable_id)
                ->where('status', DisputeStatus::Open)
                ->exists();

            if ($alreadyOpen) {
                return;
            }

            Dispute::create([
                'disputable_type' => $payment->payable_type,
                'disputable_id' => $payment->payable_id,
                'raised_by' => $raisedBy,
                'status' => DisputeStatus::Open,
                'resolution_notes' => "Stripe chargeback opened on payment {$payment->id}. Reason: {$reason}.",
            ]);
        });
    }

    private function payerIdFor(Payment $payment): ?string
    {
        $payable = $payment->payable;

        return match (true) {
            $payable instanceof Booking => $payable->customer_id,
            $payable instanceof Milestone => $payable->contract->project->client_id,
            default => null,
        };
    }
}
