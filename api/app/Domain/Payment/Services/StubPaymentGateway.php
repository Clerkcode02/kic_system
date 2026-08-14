<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Support\ValueObjects\Money;
use Illuminate\Support\Str;

final class StubPaymentGateway implements PaymentGateway
{
    public function createBookingPaymentIntent(
        Money $amount,
        Money $applicationFeeAmount,
        string $connectedAccountId,
        array $metadata = [],
    ): PaymentIntentResult {
        return $this->fakeIntent();
    }

    public function createMilestonePaymentIntent(Money $amount, array $metadata = []): PaymentIntentResult
    {
        return $this->fakeIntent();
    }

    public function createTransfer(
        Money $amount,
        string $connectedAccountId,
        string $idempotencyKey,
        array $metadata = [],
    ): TransferResult {
        return new TransferResult(transferId: 'tr_stub_'.Str::random(24));
    }

    public function refund(string $stripePaymentIntentId, Money $amount, string $idempotencyKey): RefundResult
    {
        return new RefundResult(refundId: 're_stub_'.Str::random(24));
    }

    private function fakeIntent(): PaymentIntentResult
    {
        $id = 'pi_stub_'.Str::random(24);

        return new PaymentIntentResult(
            intentId: $id,
            clientSecret: $id.'_secret_'.Str::random(16),
        );
    }
}
