<?php

declare(strict_types=1);

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Events\GuestBookingsClaimed;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Services\BookingAccessTokenService;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ValueObjects\BookingActor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * SRS §6.1 "Claiming": attaches a verified account's guest bookings to it.
 *
 * **On email verification only.** Not on registration, not on login
 * (CLAUDE.md §2). Anyone can type any email into a registration form; only
 * proving control of the mailbox may hand over bookings that carry
 * payments, addresses and a phone number. An account that registered but
 * never verified claims nothing.
 *
 * Claiming also revokes the bookings' access tokens: once an account owns
 * the booking, the anonymous credential — which may be sitting in an old
 * email, a browser history entry, or a forwarded message — must stop
 * working.
 */
final class ClaimGuestBookings implements Action
{
    public function __construct(private readonly BookingAccessTokenService $tokens)
    {
    }

    /**
     * @return list<string> the booking numbers claimed
     */
    public function handle(User $user): array
    {
        // Belt and braces: callers are expected to only reach here after
        // verification, but the rule is important enough to enforce at the
        // Action, which is the actual source of truth.
        if (! $user->hasVerifiedEmail()) {
            return [];
        }

        $normalized = BookingActor::normalizeEmail($user->email);

        return DB::transaction(function () use ($user, $normalized) {
            $bookings = Booking::query()
                ->where('guest_email_normalized', $normalized)
                ->whereNull('claimed_at')
                ->lockForUpdate()
                ->get();

            if ($bookings->isEmpty()) {
                return [];
            }

            $revoked = 0;
            $numbers = [];

            foreach ($bookings as $booking) {
                // The guest columns must be cleared in the same statement
                // that sets customer_id, or `bookings_exactly_one_actor`
                // rejects the row — which is precisely the constraint
                // doing its job.
                $booking->forceFill([
                    'customer_id' => $user->id,
                    'claimed_by_user_id' => $user->id,
                    'claimed_at' => Date::now(),
                    'guest_name' => null,
                    'guest_email' => null,
                    'guest_phone' => null,
                    'guest_email_normalized' => null,
                ])->save();

                $revoked += $this->tokens->revokeAllFor($booking);
                $numbers[] = $booking->booking_number;
            }

            GuestBookingsClaimed::dispatch($user, $numbers, $revoked);

            return $numbers;
        });
    }
}
