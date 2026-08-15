<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Illuminate\Support\Collection<string, mixed>
 */
class ProviderDashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'today_schedule' => BookingListResource::collection($this->resource['today_schedule']),
            'pending_quotations' => BookingListResource::collection($this->resource['pending_quotations']),
            'upcoming_bookings' => BookingListResource::collection($this->resource['upcoming_bookings']),
            'earnings' => [
                'total' => $this->resource['earnings_total']->toDecimal(),
                'currency' => 'CAD',
                'recent_payouts' => PayoutResource::collection($this->resource['earnings_recent']),
            ],
        ];
    }
}
