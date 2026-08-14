<?php

declare(strict_types=1);

namespace App\Domain\Booking\Queries;

use App\Domain\Booking\Models\Booking;

/**
 * GET /bookings/{id} — includes status history and attachments per the
 * endpoint contract.
 */
final class BookingDetailQuery
{
    public function handle(string $bookingId): Booking
    {
        return Booking::query()
            ->with([
                'service:id,title,pricing_type,base_price,currency',
                'provider:id,legal_name,rating_avg',
                'customer:id,name',
                'address',
                'statusHistory' => fn ($q) => $q->orderBy('created_at'),
                'attachments' => fn ($q) => $q->orderBy('created_at'),
            ])
            ->findOrFail($bookingId);
    }
}
