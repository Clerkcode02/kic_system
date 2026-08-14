<?php

declare(strict_types=1);

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Support\Action;
use App\Support\ConflictException;
use Illuminate\Support\Facades\Date;

/**
 * The provider half of the compound `InProgress --> Completed` edge (SRS
 * §8: "provider marks complete + customer confirms"). Records
 * `provider_completed_at` without moving `status` — ConfirmBookingCompletion
 * is what actually fires the state machine transition, gated on this
 * timestamp being set.
 */
final class MarkProviderComplete implements Action
{
    public function handle(Booking $booking): Booking
    {
        if ($booking->status !== BookingStatus::InProgress) {
            throw new ConflictException(
                'Only a booking that is in progress can be marked complete.',
                'booking_not_in_progress',
            );
        }

        $booking->update(['provider_completed_at' => Date::now()]);

        return $booking->refresh();
    }
}
