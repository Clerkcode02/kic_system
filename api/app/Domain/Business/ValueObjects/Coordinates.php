<?php

declare(strict_types=1);

namespace App\Domain\Business\ValueObjects;

final class Coordinates
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
    ) {
    }
}
