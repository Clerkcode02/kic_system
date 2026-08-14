<?php

declare(strict_types=1);

namespace App\Domain\Payment\Listeners;

use App\Domain\Booking\Models\Booking;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;

/**
 * `payments` is polymorphic over booking | milestone (CLAUDE.md §5) — the
 * payer is the booking's customer or, for an escrowed milestone, the
 * contract's project client.
 */
trait ResolvesPayer
{
    private function resolvePayer(Payment $payment): ?User
    {
        $payable = $payment->payable;

        if ($payable instanceof Booking) {
            return $payable->customer;
        }

        if ($payable instanceof Milestone) {
            return $payable->contract?->project?->client;
        }

        return null;
    }
}
