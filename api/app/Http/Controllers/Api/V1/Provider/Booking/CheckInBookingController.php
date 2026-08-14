<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Booking;

use App\Domain\Booking\Actions\TransitionBookingStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CheckInBookingRequest;
use App\Http\Resources\BookingResource;

class CheckInBookingController extends Controller
{
    public function __invoke(CheckInBookingRequest $request, Booking $booking, TransitionBookingStatus $action): BookingResource
    {
        $booking = $action->handle($booking, BookingStatus::InProgress, $request->user(), 'Provider checked in.');

        return new BookingResource($booking);
    }
}
