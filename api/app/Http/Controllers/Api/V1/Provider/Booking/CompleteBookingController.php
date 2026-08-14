<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Booking;

use App\Domain\Booking\Actions\MarkProviderComplete;
use App\Domain\Booking\Models\Booking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CompleteBookingRequest;
use App\Http\Resources\BookingResource;

class CompleteBookingController extends Controller
{
    public function __invoke(CompleteBookingRequest $request, Booking $booking, MarkProviderComplete $action): BookingResource
    {
        return new BookingResource($action->handle($booking));
    }
}
