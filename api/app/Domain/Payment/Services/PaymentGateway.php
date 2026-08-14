<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Support\ValueObjects\Money;

/**
 * Boundary between domain Actions and the payment provider (CLAUDE.md §7 —
 * Stripe Connect). Bound to {@see StripePaymentService}. Every intent this
 * gateway creates is CAD — currency is never a caller-supplied parameter
 * (CLAUDE.md §5 "Market scope" — Canada-only launch).
 */
interface PaymentGateway
{
    /**
     * Booking payments are destination charges: funds land on the
     * provider's connected account immediately, minus the platform's
     * application fee.
     *
     * @param  array<string, string>  $metadata
     */
    public function createBookingPaymentIntent(
        Money $amount,
        Money $applicationFeeAmount,
        string $connectedAccountId,
        array $metadata = [],
    ): PaymentIntentResult;

    /**
     * Milestone payments are escrow: funds are charged into the platform's
     * own Stripe balance (no transfer_data) and held until the milestone
     * is approved, at which point a separate Transfer moves them out.
     *
     * @param  array<string, string>  $metadata
     */
    public function createMilestonePaymentIntent(Money $amount, array $metadata = []): PaymentIntentResult;

    /**
     * Moves escrowed funds out of the platform balance to a connected
     * account (milestone release). `$idempotencyKey` is passed straight to
     * Stripe so a retried job/action can never create a second transfer for
     * the same logical release, even across separate PHP-process attempts.
     *
     * @param  array<string, string>  $metadata
     */
    public function createTransfer(
        Money $amount,
        string $connectedAccountId,
        string $idempotencyKey,
        array $metadata = [],
    ): TransferResult;

    /**
     * Refunds all or part of a succeeded PaymentIntent.
     */
    public function refund(string $stripePaymentIntentId, Money $amount, string $idempotencyKey): RefundResult;
}
