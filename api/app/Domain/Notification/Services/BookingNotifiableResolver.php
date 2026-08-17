<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Booking\Models\Booking;
use App\Domain\User\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * SRS §6.1: every notification a registered customer receives about a
 * booking has a guest equivalent. Rather than each listener asking "is this
 * a guest?" and forking, they all ask here for *the* notifiable for a
 * booking's customer side and send to whatever comes back.
 *
 * A guest gets an on-demand mail route. **No placeholder users row is ever
 * created** (CLAUDE.md §2) — that would put an unusable, unverifiable
 * account into the users table and make every "is this a real customer?"
 * query wrong.
 */
final class BookingNotifiableResolver
{
    public function forCustomer(Booking $booking): User|AnonymousNotifiable|null
    {
        if (! $booking->isGuest()) {
            return $booking->customer;
        }

        $email = $booking->contactEmail();

        if ($email === '') {
            return null;
        }

        return Notification::route('mail', $email);
    }
}
