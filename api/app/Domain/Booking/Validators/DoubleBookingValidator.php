<?php

declare(strict_types=1);

namespace App\Domain\Booking\Validators;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guards against two customers booking the same provider slot concurrently.
 *
 * A plain read-then-write check has a race: two requests can both read "no
 * conflicting booking exists" before either has inserted its row. Since the
 * conflicting row doesn't exist yet at read time there's nothing to
 * `lockForUpdate()`, and this schema doesn't have the `btree_gist`
 * extension enabled for a `tsrange` EXCLUDE constraint — so this takes a
 * Postgres session-scoped-to-transaction advisory lock
 * (`pg_advisory_xact_lock`) keyed by provider + date. It serializes every
 * concurrent transaction targeting that provider's day (not just an exact
 * slot match, so partially-overlapping requests are caught too): the
 * second caller blocks until the first commits (or rolls back), then
 * re-runs the overlap check and finds the just-committed row, converting
 * the race into a deterministic "one succeeds, one gets a 422".
 *
 * Must be called from inside the same DB::transaction() that ultimately
 * inserts the booking — the lock releases automatically at commit/rollback.
 */
final class DoubleBookingValidator
{
    /**
     * @var list<BookingStatus>
     */
    private const NON_BLOCKING_STATUSES = [
        BookingStatus::Declined,
        BookingStatus::QuotationExpired,
        BookingStatus::Cancelled,
        BookingStatus::Refunded,
    ];

    public function assertAvailable(
        Business $provider,
        CarbonImmutable $date,
        string $timeSlotStart,
        string $timeSlotEnd,
        ?string $ignoreBookingId = null,
    ): void {
        DB::statement(
            'SELECT pg_advisory_xact_lock(hashtext(?))',
            [$this->lockKey($provider, $date)]
        );

        $conflict = Booking::query()
            ->where('provider_id', $provider->id)
            ->where('scheduled_date', $date->toDateString())
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
            ->when($ignoreBookingId !== null, fn ($q) => $q->whereKeyNot($ignoreBookingId))
            ->where('time_slot_start', '<', $timeSlotEnd)
            ->where('time_slot_end', '>', $timeSlotStart)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'time_slot_start' => 'This slot was just booked by another customer. Please choose a different time.',
            ]);
        }
    }

    private function lockKey(Business $provider, CarbonImmutable $date): string
    {
        return "booking:{$provider->id}:{$date->toDateString()}";
    }
}
