<?php

declare(strict_types=1);

namespace App\Domain\Business\Queries;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Payment\Models\Payout;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * GET /provider/me/dashboard — a single composite read for the provider
 * home screen (today's schedule, pending quotation requests, upcoming
 * bookings, earnings summary), each list capped small since this is a
 * glance view, not a full booking queue (that's GET /bookings?role=provider).
 */
final class ProviderDashboardSummaryQuery
{
    private const LIST_LIMIT = 10;

    /**
     * @return array{
     *     today_schedule: Collection<int, Booking>,
     *     pending_quotations: Collection<int, Booking>,
     *     upcoming_bookings: Collection<int, Booking>,
     *     earnings_total: Money,
     *     earnings_recent: Collection<int, Payout>,
     * }
     */
    public function handle(Business $business): array
    {
        $today = Carbon::today();

        $baseQuery = fn () => Booking::query()
            ->where('provider_id', $business->id)
            ->with(['service:id,title,pricing_type', 'customer:id,name']);

        $todaySchedule = $baseQuery()
            ->whereDate('scheduled_date', $today)
            ->orderBy('scheduled_date')
            ->orderBy('time_slot_start')
            ->limit(self::LIST_LIMIT)
            ->get();

        $pendingQuotations = $baseQuery()
            ->where('status', BookingStatus::WaitingForQuotation)
            ->orderBy('created_at')
            ->limit(self::LIST_LIMIT)
            ->get();

        $upcomingBookings = $baseQuery()
            ->where('status', BookingStatus::Scheduled)
            ->where('scheduled_date', '>', $today)
            ->orderBy('scheduled_date')
            ->orderBy('time_slot_start')
            ->limit(self::LIST_LIMIT)
            ->get();

        $payouts = Payout::query()
            ->where('provider_id', $business->id)
            ->orderByDesc('created_at')
            ->limit(self::LIST_LIMIT)
            ->get();

        $earningsTotal = Payout::query()
            ->where('provider_id', $business->id)
            ->get()
            ->reduce(
                fn (?Money $carry, Payout $payout) => $carry === null ? $payout->amount : $carry->add($payout->amount),
                null,
            ) ?? Money::fromMinorUnits(0, 'CAD');

        return [
            'today_schedule' => $todaySchedule,
            'pending_quotations' => $pendingQuotations,
            'upcoming_bookings' => $upcomingBookings,
            'earnings_total' => $earningsTotal,
            'earnings_recent' => $payouts,
        ];
    }
}
