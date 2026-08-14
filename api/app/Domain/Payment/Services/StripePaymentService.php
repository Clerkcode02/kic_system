<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Support\ValueObjects\Money;
use Stripe\StripeClient;

/**
 * Real Stripe implementation of {@see PaymentGateway}, replacing the Phase 5
 * {@see StubPaymentGateway}. `currency` is a hardcoded constant, never
 * threaded through as a parameter — CLAUDE.md §5 fixes this platform to
 * CAD for launch.
 */
final class StripePaymentService implements PaymentGateway
{
    private const CURRENCY = 'cad';

    public function __construct(private readonly StripeClient $stripe)
    {
    }

    public function createBookingPaymentIntent(
        Money $amount,
        Money $applicationFeeAmount,
        string $connectedAccountId,
        array $metadata = [],
    ): PaymentIntentResult {
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $amount->minorUnits,
            'currency' => self::CURRENCY,
            'application_fee_amount' => $applicationFeeAmount->minorUnits,
            'transfer_data' => [
                'destination' => $connectedAccountId,
            ],
            'metadata' => $metadata,
        ]);

        return new PaymentIntentResult(intentId: $intent->id, clientSecret: $intent->client_secret);
    }

    public function createMilestonePaymentIntent(Money $amount, array $metadata = []): PaymentIntentResult
    {
        // No transfer_data — funds land on the platform's own balance and
        // stay there as escrow until ApproveMilestone triggers a Transfer.
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $amount->minorUnits,
            'currency' => self::CURRENCY,
            'metadata' => $metadata,
        ]);

        return new PaymentIntentResult(intentId: $intent->id, clientSecret: $intent->client_secret);
    }

    public function createTransfer(
        Money $amount,
        string $connectedAccountId,
        string $idempotencyKey,
        array $metadata = [],
    ): TransferResult {
        $transfer = $this->stripe->transfers->create([
            'amount' => $amount->minorUnits,
            'currency' => self::CURRENCY,
            'destination' => $connectedAccountId,
            'metadata' => $metadata,
        ], [
            'idempotency_key' => $idempotencyKey,
        ]);

        return new TransferResult(transferId: $transfer->id);
    }

    public function refund(string $stripePaymentIntentId, Money $amount, string $idempotencyKey): RefundResult
    {
        $refund = $this->stripe->refunds->create([
            'payment_intent' => $stripePaymentIntentId,
            'amount' => $amount->minorUnits,
        ], [
            'idempotency_key' => $idempotencyKey,
        ]);

        return new RefundResult(refundId: $refund->id);
    }
}
