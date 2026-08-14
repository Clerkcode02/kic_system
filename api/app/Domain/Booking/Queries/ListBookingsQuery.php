<?php

declare(strict_types=1);

namespace App\Domain\Booking\Queries;

use App\Domain\Booking\Models\Booking;
use App\Domain\User\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /bookings?role=customer|provider&status=&cursor= — `role` picks which
 * side of the transaction the caller is viewing themselves as; it's
 * validated against actual ownership here, not trusted from the query
 * string alone (a provider owner passing role=customer just gets an empty
 * result set rather than another customer's bookings).
 */
final class ListBookingsQuery
{
    private const PER_PAGE = 20;

    /**
     * @param  array{role: string, status?: string, cursor?: string}  $filters
     * @return CursorPaginator<int, Booking>
     */
    public function handle(User $user, array $filters): CursorPaginator
    {
        $query = Booking::query()->with(['service:id,title,pricing_type', 'provider:id,legal_name', 'customer:id,name']);

        if ($filters['role'] === 'provider') {
            $providerId = $user->business?->id;

            if ($providerId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('provider_id', $providerId);
            }
        } else {
            $query->where('customer_id', $user->id);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
