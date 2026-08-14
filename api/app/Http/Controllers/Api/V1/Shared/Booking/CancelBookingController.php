<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Booking;

use App\Domain\Booking\Actions\CancelBooking;
use App\Domain\Booking\Models\Booking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Resources\BookingResource;

class CancelBookingController extends Controller
{
    public function __invoke(CancelBookingRequest $request, Booking $booking, CancelBooking $action): BookingResource
    {
        $result = $action->handle($booking, $request->user(), $request->validated('reason'));

        return (new BookingResource($result['booking']))
            ->additional(['meta' => ['cancellation_fee_applied' => $result['cancellation_fee_applied']]]);
    }
}
