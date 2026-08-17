<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Guest;

use App\Domain\Booking\Models\Booking;
use App\Http\Middleware\ResolveBookingActor;
use App\Support\ValueObjects\BookingActor;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Reads what {@see ResolveBookingActor} put on the request. The middleware
 * is the only thing that decides who may see which booking (SRS §6.1), so
 * these accessors deliberately have no fallback: if the attribute is
 * missing, the route was misconfigured without the middleware and must
 * fail loudly rather than quietly resolving something less safe.
 */
trait ResolvesGuestBooking
{
    protected function guestBooking(Request $request): Booking
    {
        $booking = $request->attributes->get(ResolveBookingActor::BOOKING_ATTRIBUTE);

        if (! $booking instanceof Booking) {
            throw new RuntimeException('Route is missing the ResolveBookingActor middleware.');
        }

        return $booking;
    }

    protected function guestActor(Request $request): BookingActor
    {
        $actor = $request->attributes->get(ResolveBookingActor::ACTOR_ATTRIBUTE);

        if (! $actor instanceof BookingActor) {
            throw new RuntimeException('Route is missing the ResolveBookingActor middleware.');
        }

        return $actor;
    }
}
