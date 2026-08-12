<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Money is always decimal(12,2) in the database and an integer-minor-units
 * value object in PHP — never a float (CLAUDE.md §2/§6).
 */
final class Money implements Stringable
{
    private function __construct(
        public readonly int $minorUnits,
        public readonly string $currency,
    ) {
    }

    public static function fromMinorUnits(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, strtoupper($currency));
    }

    /**
     * @param  string  $decimal  e.g. "1234.50" — as read verbatim from a decimal(12,2) column
     */
    public static function fromDecimal(string $decimal, string $currency): self
    {
        $negative = str_starts_with($decimal, '-');
        $unsigned = ltrim($negative ? substr($decimal, 1) : $decimal, '+');

        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        $minorUnits = ((int) $whole) * 100 + (int) $fraction;

        return new self($negative ? -$minorUnits : $minorUnits, strtoupper($currency));
    }

    public function toDecimal(): string
    {
        $negative = $this->minorUnits < 0;
        $absolute = abs($this->minorUnits);

        $whole = intdiv($absolute, 100);
        $fraction = $absolute % 100;

        return ($negative ? '-' : '').$whole.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits && $this->currency === $other->currency;
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function __toString(): string
    {
        return $this->toDecimal().' '.$this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on Money in different currencies: {$this->currency} vs {$other->currency}"
            );
        }
    }
}
