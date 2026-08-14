<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Booking;

use App\Domain\Booking\Actions\ConfirmBookingCompletion;
use App\Domain\Booking\Models\Booking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\ConfirmBookingCompletionRequest;
use App\Http\Resources\BookingResource;

class ConfirmBookingCompletionController extends Controller
{
    public function __invoke(ConfirmBookingCompletionRequest $request, Booking $booking, ConfirmBookingCompletion $action): BookingResource
    {
        return new BookingResource($action->handle($booking, $request->user()));
    }
}
