<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Services\BookingAccessTokenService;
use App\Support\ValueObjects\BookingActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SRS §6.1: resolves who is acting on a booking — `auth:sanctum` first,
 * then the `X-Booking-Token` header — and puts both the resolved
 * {@see BookingActor} and the booking it authorizes on the request.
 *
 * Header-first by construction: the token is read from a header and never
 * from a cookie or the session, so no cookie/CSRF assumption reaches domain
 * code (CLAUDE.md §9 item 4) and the whole guest lifecycle works with no
 * cookies at all.
 *
 * Every failure — missing, unknown, expired, revoked, or scoped to a
 * different booking — is a 404. Never a 403: a 403 would confirm the
 * booking exists.
 */
class ResolveBookingActor
{
    public const ACTOR_ATTRIBUTE = 'booking_actor';

    public const BOOKING_ATTRIBUTE = 'resolved_booking';

    public const HEADER = 'X-Booking-Token';

    public function __construct(private readonly BookingAccessTokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // The route may name the booking (`/guest/bookings/{bookingNumber}`)
        // or not (`/guest/quotations/{id}/accept`, where the token itself
        // determines which booking is in play).
        $bookingNumber = $request->route('bookingNumber');

        // Explicitly the sanctum guard: this route is not behind
        // `auth:sanctum`, so the default (session `web`) guard would be
        // consulted instead and would miss a Bearer-token caller.
        $user = $request->user('sanctum');

        if ($user !== null) {
            $actor = BookingActor::user($user);
            $booking = is_string($bookingNumber)
                ? Booking::query()->where('booking_number', $bookingNumber)->first()
                : null;

            // A registered user reaching a guest route is fine, but they
            // may only see their own booking — same 404, no ownership leak.
            //
            // No booking at all is also a 404: on the routes that don't name
            // one (`/guest/quotations/{id}/accept`, `/guest/payments/intents`)
            // the *token* is what identifies the booking, so an
            // authenticated caller has supplied nothing to act on. They
            // have their own endpoints for that.
            if ($booking === null || (! $actor->owns($booking) && ! $actor->isAdmin())) {
                return $this->notFound();
            }

            return $this->pass($request, $next, $actor, $booking);
        }

        $plaintext = (string) $request->header(self::HEADER, '');

        $booking = $plaintext === ''
            ? null
            : $this->tokens->resolve($plaintext, is_string($bookingNumber) ? $bookingNumber : null);

        if ($booking === null) {
            return $this->notFound();
        }

        return $this->pass($request, $next, $booking->actor(), $booking);
    }

    private function pass(Request $request, Closure $next, BookingActor $actor, ?Booking $booking): Response
    {
        $request->attributes->set(self::ACTOR_ATTRIBUTE, $actor);

        if ($booking !== null) {
            $request->attributes->set(self::BOOKING_ATTRIBUTE, $booking);
        }

        return $next($request);
    }

    private function notFound(): Response
    {
        return response()->json(['message' => 'Resource not found.'], 404);
    }
}
