<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Generic 409 for write operations blocked by current data state — a
 * category with dependents, a cyclical parent move, etc. Distinct from
 * {@see IllegalStateTransitionException}, which is specifically for
 * StateMachine transitions.
 */
final class ConflictException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }
}
