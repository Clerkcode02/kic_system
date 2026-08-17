<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Guest;

use App\Domain\Booking\Actions\CancelBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\CancelGuestBookingRequest;
use App\Http\Resources\GuestBookingResource;
use Illuminate\Http\Request;

/**
 * SRS §6.1: the tracked-booking surface a booking access token opens.
 * `ResolveBookingActor` has already resolved (or 404'd) both the actor and
 * the booking, so nothing here re-checks ownership — and nothing here
 * accepts a booking number as an authorization input.
 *
 * Both methods delegate to the same shared Actions the registered path
 * uses; there is no guest-specific business logic.
 */
class GuestBookingController extends Controller
{
    use ResolvesGuestBooking;

    public function show(Request $request): GuestBookingResource
    {
        $booking = $this->guestBooking($request)->load([
            'service',
            'provider',
            'statusHistory',
            'quotations.lineItems',
        ]);

        return new GuestBookingResource($booking);
    }

    public function cancel(CancelGuestBookingRequest $request, CancelBooking $action): GuestBookingResource
    {
        $result = $action->handle(
            $this->guestBooking($request),
            $this->guestActor($request),
            $request->validated('reason'),
        );

        $booking = $result['booking']->load([
            'service',
            'provider',
            'statusHistory',
            'quotations.lineItems',
        ]);

        return (new GuestBookingResource($booking))
            ->additional(['meta' => ['cancellation_fee_applied' => $result['cancellation_fee_applied']]]);
    }
}
