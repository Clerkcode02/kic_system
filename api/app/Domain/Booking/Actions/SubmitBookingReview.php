<?php

declare(strict_types=1);

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Review\Actions\SubmitReview;
use App\Domain\Review\Models\Review;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;

/**
 * SRS §19: reviews are only after completion, one per completed
 * transaction. This Action owns those two checks for bookings before
 * delegating to the generic SubmitReview write path.
 */
final class SubmitBookingReview implements Action
{
    public function __construct(private readonly SubmitReview $submitReview)
    {
    }

    public function handle(Booking $booking, User $reviewer, int $rating, ?string $comment): Review
    {
        if ($booking->status !== BookingStatus::Completed) {
            throw new ConflictException('Reviews can only be left after the booking is completed.', 'booking_not_completed');
        }

        if ($booking->reviews()->where('reviewer_id', $reviewer->id)->exists()) {
            throw new ConflictException('You have already reviewed this booking.', 'duplicate_review');
        }

        return $this->submitReview->handle($reviewer, $booking->provider->user, $booking, $rating, $comment);
    }
}
