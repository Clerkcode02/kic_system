<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Booking;

use App\Domain\Booking\Actions\CreateBookingRequest as CreateBookingRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use Illuminate\Http\JsonResponse;

class StoreBookingController extends Controller
{
    public function __invoke(StoreBookingRequest $request, CreateBookingRequestAction $action): JsonResponse
    {
        $booking = $action->handle($request->user(), $request->validated());

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode(201);
    }
}
