<?php

declare(strict_types=1);

namespace App\Domain\Booking\Events;

use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SRS §6.1 "Claiming": guest bookings were attached to a newly verified
 * account. Auditable because ownership of paid transactions changed hands —
 * exactly the kind of critical action §13 exists to record.
 *
 * Recorded against the *user*, not per booking: one verification is one
 * decision, and the claimed booking numbers are carried in the after-state
 * diff so the trail stays a single row rather than N.
 */
class GuestBookingsClaimed implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<string>  $bookingNumbers
     */
    public function __construct(
        public readonly User $user,
        public readonly array $bookingNumbers,
        public readonly int $tokensRevoked,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->user->id;
    }

    public function auditAction(): string
    {
        return 'booking.guest_bookings_claimed';
    }

    public function auditableType(): string
    {
        return 'user';
    }

    public function auditableId(): string
    {
        return $this->user->id;
    }

    public function auditBeforeState(): ?array
    {
        return ['claimed_bookings' => 0];
    }

    public function auditAfterState(): ?array
    {
        return [
            'claimed_bookings' => count($this->bookingNumbers),
            // Booking numbers, not ids or emails — the public identifier is
            // enough to investigate with and leaks nothing extra.
            'booking_numbers' => $this->bookingNumbers,
            'access_tokens_revoked' => $this->tokensRevoked,
        ];
    }
}
