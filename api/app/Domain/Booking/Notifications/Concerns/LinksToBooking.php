<?php

declare(strict_types=1);

namespace App\Domain\Booking\Notifications\Concerns;

use App\Domain\Booking\Models\Booking;

/**
 * SRS §6.1: the customer-facing deep link for a booking differs by actor
 * kind. A registered customer goes to their dashboard; a guest has no
 * dashboard, so they go to the public tracking page with the booking number
 * prefilled.
 *
 * The tracking link here deliberately carries **no token**. Access tokens
 * are not recoverable from storage, so only the two emails that are sent at
 * the moment one is minted — booking confirmation and lookup — can include
 * a live link. Every other email lands the guest on /track, where the
 * lookup form re-issues one to their mailbox.
 */
trait LinksToBooking
{
    protected function customerBookingUrl(Booking $booking): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return $booking->isGuest()
            ? $base.'/track?booking='.urlencode($booking->booking_number)
            : $base.'/bookings/'.$booking->id;
    }
}
