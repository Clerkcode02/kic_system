<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Booking\Models\Booking;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentGateway;
use App\Support\Action;
use App\Support\ConflictException;
use App\Support\PaymentsBlockedException;
use App\Support\ValueObjects\BookingActor;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * POST /payments/intents (CLAUDE.md §7 item 4). Generic payment-intent
 * creation for the two payable kinds — a booking's still-unpaid balance
 * (deposit/remainder/full, whichever applies) or a milestone's escrow
 * funding. The amount is always recomputed from persisted rows here; the
 * client never supplies one.
 */
final class CreatePaymentIntent implements Action
{
    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    /**
     * @return array{payment: Payment, client_secret: ?string}
     */
    public function handle(Booking|Milestone $payable, BookingActor $actor): array
    {
        return DB::transaction(fn () => $payable instanceof Booking
            ? $this->forBooking($payable)
            : $this->forMilestone($payable));
    }

    /**
     * @return array{payment: Payment, client_secret: ?string}
     */
    private function forBooking(Booking $booking): array
    {
        if (! $booking->provider->canReceiveFunds()) {
            throw new PaymentsBlockedException(
                'This provider cannot receive payouts yet — Stripe Connect onboarding is incomplete.',
                'provider_payouts_not_enabled',
            );
        }

        $quotation = $booking->quotations()->where('status', 'accepted')->latest()->first();

        if ($quotation === null) {
            throw new ConflictException('This booking has no accepted quotation to pay.', 'no_accepted_quotation');
        }

        // Pending intents count as "already charging" too, not just
        // succeeded ones — otherwise a retry before the webhook confirms
        // the deposit would recompute the full total again instead of the
        // remainder (CLAUDE.md §19 — "idempotent, no duplicate charge").
        $alreadyRequested = Payment::query()
            ->where('payable_type', 'booking')
            ->where('payable_id', $booking->id)
            ->where('status', '!=', PaymentStatus::Failed)
            ->get()
            ->reduce(fn (?Money $carry, Payment $payment) => $carry === null ? $payment->amount : $carry->add($payment->amount), null);

        $remaining = $alreadyRequested === null ? $quotation->total_amount : $quotation->total_amount->subtract($alreadyRequested);

        if ($remaining->isZero()) {
            throw new ConflictException('This booking has already been paid in full.', 'booking_already_paid');
        }

        $isFirstLeg = $alreadyRequested === null;
        $platformFeeAmount = $isFirstLeg ? $quotation->platform_fee : $remaining->subtract($remaining);

        $type = match (true) {
            ! $isFirstLeg => PaymentType::Partial,
            $quotation->deposit_percentage !== null && (float) $quotation->deposit_percentage > 0.0 => PaymentType::Deposit,
            default => PaymentType::Full,
        };

        $intent = $this->gateway->createBookingPaymentIntent(
            $remaining,
            $platformFeeAmount,
            $booking->provider->stripe_connect_account_id,
            ['booking_id' => $booking->id, 'quotation_id' => $quotation->id],
        );

        $payment = Payment::create([
            'payable_type' => 'booking',
            'payable_id' => $booking->id,
            'stripe_payment_intent_id' => $intent->intentId,
            'amount' => $remaining,
            'platform_fee_amount' => $platformFeeAmount,
            'provider_net_amount' => $remaining->subtract($platformFeeAmount),
            'currency' => $quotation->currency,
            'type' => $type,
            'status' => PaymentStatus::Pending,
        ]);

        return ['payment' => $payment, 'client_secret' => $intent->clientSecret];
    }

    /**
     * @return array{payment: Payment, client_secret: ?string}
     */
    private function forMilestone(Milestone $milestone): array
    {
        if (! $milestone->contract->proposal->freelancer->canReceiveFunds()) {
            throw new PaymentsBlockedException(
                'This freelancer cannot receive payouts yet — Stripe Connect onboarding is incomplete.',
                'freelancer_payouts_not_enabled',
            );
        }

        $alreadyFunded = Payment::query()
            ->where('payable_type', 'milestone')
            ->where('payable_id', $milestone->id)
            ->where('status', '!=', PaymentStatus::Failed)
            ->exists();

        if ($alreadyFunded) {
            throw new ConflictException('This milestone has already been funded.', 'milestone_already_funded');
        }

        $amount = $milestone->amount;

        $intent = $this->gateway->createMilestonePaymentIntent(
            $amount,
            ['milestone_id' => $milestone->id, 'contract_id' => $milestone->contract_id],
        );

        $payment = Payment::create([
            'payable_type' => 'milestone',
            'payable_id' => $milestone->id,
            'stripe_payment_intent_id' => $intent->intentId,
            'amount' => $amount,
            'platform_fee_amount' => $amount->subtract($amount),
            'provider_net_amount' => $amount,
            'currency' => $milestone->currency,
            'type' => PaymentType::Escrow,
            'status' => PaymentStatus::Pending,
        ]);

        return ['payment' => $payment, 'client_secret' => $intent->clientSecret];
    }
}
