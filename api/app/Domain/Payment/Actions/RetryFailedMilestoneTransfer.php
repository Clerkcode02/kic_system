<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Freelance\Models\Milestone;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Events\PaymentTransferRetried;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use Illuminate\Support\Facades\DB;

/**
 * Admin retry of a milestone escrow Transfer that Stripe reported as failed
 * (see ReconcileTransfer). Resets the payment back to succeeded/untransferred
 * so ReleaseMilestoneEscrow's existing "already transferred" guard
 * (`stripe_transfer_id === null`) lets it attempt the Stripe Transfer call
 * again, reusing that Action's idempotency-keyed transfer logic rather than
 * duplicating it.
 */
final class RetryFailedMilestoneTransfer implements Action
{
    public function __construct(
        private readonly ReleaseMilestoneEscrow $releaseMilestoneEscrow,
    ) {
    }

    public function handle(Payment $payment, User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $actor) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Failed || $payment->stripe_transfer_id === null) {
                throw new ConflictException(
                    'Only a payment with a failed transfer can be retried.',
                    'payment_not_failed_transfer',
                );
            }

            if ($payment->payable_type !== 'milestone') {
                throw new ConflictException(
                    'Only milestone escrow transfers can be retried here.',
                    'payment_not_milestone',
                );
            }

            $failedTransferId = $payment->stripe_transfer_id;

            $payment->update([
                'status' => PaymentStatus::Succeeded,
                'stripe_transfer_id' => null,
            ]);

            PaymentTransferRetried::dispatch($payment, $actor, $failedTransferId);

            $milestone = Milestone::query()->findOrFail($payment->payable_id);

            $this->releaseMilestoneEscrow->handle($milestone, $actor);

            return $payment->fresh();
        });
    }
}
