<?php

declare(strict_types=1);

namespace App\Domain\Business\Services;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use Carbon\CarbonImmutable;

/**
 * Owns all provider availability slot computation (CLAUDE.md provider-management
 * spec: "Controllers do none of it."). Returns fixed-length bookable slots for
 * a single date, accounting for date overrides/blackouts, the weekly recurring
 * schedule, existing bookings, and the provider's daily booking cap.
 */
final class AvailabilityService
{
    private const SLOT_MINUTES = 30;

    /**
     * Bookings in these statuses never occupy a slot or count toward the
     * daily cap — the request never went anywhere, or the slot is free again.
     *
     * @var list<BookingStatus>
     */
    private const NON_BLOCKING_STATUSES = [
        BookingStatus::Declined,
        BookingStatus::QuotationExpired,
        BookingStatus::Cancelled,
        BookingStatus::Refunded,
    ];

    public function __construct(
        private readonly AvailabilityCache $cache,
    ) {
    }

    /**
     * @return list<array{start: string, end: string}>
     */
    public function slotsForDate(Business $business, CarbonImmutable $date): array
    {
        return $this->cache->remember(
            $business->id,
            $date->toDateString(),
            fn () => $this->computeSlots($business, $date),
        );
    }

    /**
     * @return list<array{start: string, end: string}>
     */
    private function computeSlots(Business $business, CarbonImmutable $date): array
    {
        $windows = $this->workingWindows($business, $date);

        if ($windows === []) {
            return [];
        }

        $activeBookings = Booking::query()
            ->where('provider_id', $business->id)
            ->where('scheduled_date', $date->toDateString())
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
            ->get(['time_slot_start', 'time_slot_end']);

        if ($activeBookings->count() >= $business->max_bookings_per_day) {
            return [];
        }

        $bookedRanges = $activeBookings
            ->map(fn (Booking $booking) => [
                CarbonImmutable::parse($date->toDateString().' '.$booking->time_slot_start),
                CarbonImmutable::parse($date->toDateString().' '.$booking->time_slot_end),
            ])
            ->all();

        $slots = [];

        foreach ($windows as [$windowStart, $windowEnd]) {
            $cursor = CarbonImmutable::parse($date->toDateString().' '.$windowStart);
            $windowEndsAt = CarbonImmutable::parse($date->toDateString().' '.$windowEnd);

            while (true) {
                $slotEnd = $cursor->addMinutes(self::SLOT_MINUTES);

                if ($slotEnd->greaterThan($windowEndsAt)) {
                    break;
                }

                if (! $this->overlapsAny($cursor, $slotEnd, $bookedRanges)) {
                    $slots[] = ['start' => $cursor->toIso8601String(), 'end' => $slotEnd->toIso8601String()];
                }

                $cursor = $slotEnd;
            }
        }

        return $slots;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function workingWindows(Business $business, CarbonImmutable $date): array
    {
        $override = $business->availabilityOverrides()
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($override !== null) {
            if ($override->is_blackout) {
                return [];
            }

            return [[$override->start_time, $override->end_time]];
        }

        return $business->availability()
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_active', true)
            ->get(['start_time', 'end_time'])
            ->map(fn ($row) => [$row->start_time, $row->end_time])
            ->all();
    }

    /**
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $ranges
     */
    private function overlapsAny(CarbonImmutable $start, CarbonImmutable $end, array $ranges): bool
    {
        foreach ($ranges as [$rangeStart, $rangeEnd]) {
            if ($start->lessThan($rangeEnd) && $rangeStart->lessThan($end)) {
                return true;
            }
        }

        return false;
    }
}
