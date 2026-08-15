<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Dispute\Enums\DisputeStatus;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Actions\CompleteContract;
use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentGateway;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use App\Support\Facades\Settings;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * CLAUDE.md §7 item 4: on milestone approval, transfer the escrowed funds
 * (minus the platform fee) to the freelancer's connected account, then
 * complete the milestone. Money never moves before approval (guarded
 * below), and the Transfer itself carries a milestone-derived idempotency
 * key so a retried job can never create a second one even if this
 * transaction never commits (e.g. the worker crashes between the Stripe
 * call succeeding and the DB write) — the local `stripe_transfer_id` check
 * is the fast path, Stripe's own idempotency key is the actual guarantee.
 */
final class ReleaseMilestoneEscrow implements Action
{
    private const DEFAULT_PLATFORM_FEE_PERCENTAGE = 10.0;

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly CompleteContract $completeContract,
    ) {
    }

    public function handle(Milestone $milestone, ?User $actor = null): Milestone
    {
        return DB::transaction(function () use ($milestone, $actor) {
            $milestone = Milestone::query()->lockForUpdate()->findOrFail($milestone->id);

            if ($milestone->status === MilestoneStatus::Paid) {
                return $milestone;
            }

            $hasOpenDispute = Dispute::query()
                ->where('disputable_type', 'milestone')
                ->where('disputable_id', $milestone->id)
                ->whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])
                ->exists();

            if ($hasOpenDispute) {
                throw new ConflictException(
                    'This milestone has an open dispute — escrow release is frozen until it is resolved.',
                    'milestone_escrow_frozen',
                );
            }

            if ($milestone->status !== MilestoneStatus::Approved) {
                throw new ConflictException(
                    'Milestone must be approved before its escrow can be released.',
                    'milestone_not_approved',
                );
            }

            $payment = Payment::query()
                ->lockForUpdate()
                ->where('payable_type', 'milestone')
                ->where('payable_id', $milestone->id)
                ->where('status', PaymentStatus::Succeeded)
                ->first();

            if ($payment === null) {
                throw new ConflictException(
                    'This milestone has no succeeded escrow payment to release.',
                    'milestone_not_funded',
                );
            }

            if ($payment->stripe_transfer_id === null) {
                $freelancer = $milestone->contract->proposal->freelancer;

                $platformFee = $this->proportion($milestone->amount, $this->platformFeePercentage());
                $transferAmount = $milestone->amount->subtract($platformFee);

                $transfer = $this->gateway->createTransfer(
                    $transferAmount,
                    (string) $freelancer->stripe_connect_account_id,
                    "milestone-escrow-release-{$milestone->id}",
                    ['milestone_id' => $milestone->id, 'contract_id' => $milestone->contract_id],
                );

                $payment->update([
                    'stripe_transfer_id' => $transfer->transferId,
                    'platform_fee_amount' => $platformFee,
                    'provider_net_amount' => $transferAmount,
                ]);

                AuditLog::create([
                    'actor_id' => $actor?->id,
                    'action' => 'milestone.escrow_released',
                    'auditable_type' => 'milestone',
                    'auditable_id' => $milestone->id,
                    'before_state' => ['status' => MilestoneStatus::Approved->value],
                    'after_state' => ['stripe_transfer_id' => $transfer->transferId, 'amount' => $transferAmount->toDecimal()],
                    'ip_address' => null,
                    'user_agent' => null,
                ]);
            }

            $this->completeContract->handle($milestone, $actor);

            return $milestone->fresh();
        });
    }

    private function proportion(Money $amount, float $percentage): Money
    {
        return Money::fromMinorUnits(
            (int) round($amount->minorUnits * $percentage / 100),
            $amount->currency,
        );
    }

    private function platformFeePercentage(): float
    {
        return (float) Settings::get('platform.default_fee_percentage', self::DEFAULT_PLATFORM_FEE_PERCENTAGE);
    }
}
