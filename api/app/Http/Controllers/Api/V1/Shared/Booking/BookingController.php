<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Booking;

use App\Domain\Booking\Queries\BookingDetailQuery;
use App\Domain\Booking\Queries\ListBookingsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\IndexBookingRequest;
use App\Http\Resources\BookingListResource;
use App\Http\Resources\BookingResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function index(IndexBookingRequest $request, ListBookingsQuery $query): AnonymousResourceCollection
    {
        return BookingListResource::collection($query->handle($request->user(), $request->validated()));
    }

    public function show(Request $request, string $booking, BookingDetailQuery $query): BookingResource
    {
        $booking = $query->handle($booking);

        abort_unless($request->user()?->can('view', $booking), 403);

        return new BookingResource($booking);
    }
}
