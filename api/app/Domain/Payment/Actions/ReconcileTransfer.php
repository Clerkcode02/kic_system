<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Support\Action;
use Illuminate\Support\Facades\DB;

/**
 * Reacts to `transfer.paid` / `transfer.failed`. ReleaseMilestoneEscrow
 * already marks the milestone `paid` synchronously once the Transfer API
 * call itself succeeds (Stripe Transfers don't have an async pending state
 * the way PaymentIntents do), so `transfer.paid` here is just a settlement
 * confirmation. `transfer.failed` is the one that needs to actually do
 * something: flag the payment so an admin can investigate/reverse.
 */
final class ReconcileTransfer implements Action
{
    public function handle(string $stripeTransferId, bool $succeeded): void
    {
        DB::transaction(function () use ($stripeTransferId, $succeeded) {
            $payment = Payment::query()
                ->lockForUpdate()
                ->where('stripe_transfer_id', $stripeTransferId)
                ->first();

            if ($payment === null || $succeeded) {
                return;
            }

            if ($payment->status === PaymentStatus::Failed) {
                return;
            }

            $before = $payment->status->value;
            $payment->update(['status' => PaymentStatus::Failed]);

            AuditLog::create([
                'actor_id' => null,
                'action' => 'payment.transfer_failed',
                'auditable_type' => 'payment',
                'auditable_id' => $payment->id,
                'before_state' => ['status' => $before],
                'after_state' => ['status' => PaymentStatus::Failed->value, 'stripe_transfer_id' => $stripeTransferId],
                'ip_address' => null,
                'user_agent' => null,
            ]);
        });
    }
}
