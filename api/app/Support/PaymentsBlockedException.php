<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * 403 for write operations blocked because the receiving party's Stripe
 * Connect account can't accept funds yet (CLAUDE.md §7 — "Block quotation
 * sending and proposal hiring for accounts that can't receive funds").
 */
final class PaymentsBlockedException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }
}
