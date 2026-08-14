<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

final class PaymentIntentResult
{
    public function __construct(
        public readonly string $intentId,
        public readonly ?string $clientSecret,
    ) {
    }
}
