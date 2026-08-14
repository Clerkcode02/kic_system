<?php

declare(strict_types=1);

namespace App\Domain\Booking\Validators;

use App\Domain\Business\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Confirms a requested [start, end) window sits entirely inside the
 * provider's bookable hours for that date — the weekly recurring schedule,
 * overridden by a same-date blackout/override row when one exists.
 *
 * Deliberately independent of AvailabilityService: that service quantizes
 * the day into fixed 30-minute slots for calendar UI, which can't answer
 * "does this arbitrary-length booking request fit" without re-deriving the
 * same working windows anyway.
 */
final class BookingAvailabilityValidator
{
    public function assertWithinAvailability(
        Business $provider,
        CarbonImmutable $date,
        string $timeSlotStart,
        string $timeSlotEnd,
    ): void {
        $windows = $this->workingWindows($provider, $date);

        foreach ($windows as [$windowStart, $windowEnd]) {
            if ($timeSlotStart >= $windowStart && $timeSlotEnd <= $windowEnd) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'time_slot_start' => 'This provider is not available for the requested date and time.',
        ]);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function workingWindows(Business $provider, CarbonImmutable $date): array
    {
        $override = $provider->availabilityOverrides()
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($override !== null) {
            if ($override->is_blackout) {
                return [];
            }

            return [[$override->start_time, $override->end_time]];
        }

        return $provider->availability()
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_active', true)
            ->get(['start_time', 'end_time'])
            ->map(fn ($row) => [$row->start_time, $row->end_time])
            ->all();
    }
}
