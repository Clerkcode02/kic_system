<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Review;

use App\Domain\Booking\Actions\SubmitBookingReview;
use App\Domain\Booking\Models\Booking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreBookingReviewRequest;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\JsonResponse;

class BookingReviewController extends Controller
{
    public function __invoke(StoreBookingReviewRequest $request, Booking $booking, SubmitBookingReview $action): JsonResponse
    {
        $validated = $request->validated();

        $review = $action->handle($booking, $request->user(), (int) $validated['rating'], $validated['comment'] ?? null);

        return (new ReviewResource($review))
            ->response()
            ->setStatusCode(201);
    }
}
