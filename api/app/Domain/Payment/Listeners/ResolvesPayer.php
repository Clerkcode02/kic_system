<?php

declare(strict_types=1);

namespace App\Domain\Payment\Listeners;

use App\Domain\Booking\Models\Booking;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Notification\Services\BookingNotifiableResolver;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * `payments` is polymorphic over booking | milestone (CLAUDE.md §5) — the
 * payer is the booking's customer or, for an escrowed milestone, the
 * contract's project client.
 *
 * A booking's payer may be a guest with no users row (SRS §6.1), in which
 * case this resolves to a mail-only on-demand notifiable. Milestones are
 * freelance scope, which is account-only, so that branch is always a User.
 */
trait ResolvesPayer
{
    private function resolvePayer(Payment $payment): User|AnonymousNotifiable|null
    {
        $payable = $payment->payable;

        if ($payable instanceof Booking) {
            return app(BookingNotifiableResolver::class)->forCustomer($payable);
        }

        if ($payable instanceof Milestone) {
            return $payable->contract?->project?->client;
        }

        return null;
    }
}
