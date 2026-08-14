<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

final class TransferResult
{
    public function __construct(
        public readonly string $transferId,
    ) {
    }
}
