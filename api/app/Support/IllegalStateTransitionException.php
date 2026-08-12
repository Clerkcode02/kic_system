<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class IllegalStateTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        ?string $entity = null,
    ) {
        $subject = $entity !== null ? "{$entity} " : '';

        parent::__construct("Cannot transition {$subject}from [{$from}] to [{$to}].");
    }
}
