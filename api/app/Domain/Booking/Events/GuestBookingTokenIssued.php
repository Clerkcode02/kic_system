<?php

declare(strict_types=1);

namespace App\Domain\Booking\Events;

use App\Domain\Booking\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SRS §6.1: a booking access token was minted and must be emailed as a
 * tracking link. Fired on guest booking creation and on a successful
 * lookup — the only two moments a plaintext token exists.
 *
 * Deliberately **not** `Auditable`: the audit trail records that a guest
 * booking was created (BookingCreated), which is the fact worth keeping.
 * Recording token issuance would put a credential-adjacent event into an
 * insert-only table for no investigative benefit.
 *
 * The plaintext travels on the event rather than being looked up because it
 * is not recoverable — only its sha256 is stored (CLAUDE.md §2).
 */
class GuestBookingTokenIssued
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $plaintextToken,
    ) {
    }
}
