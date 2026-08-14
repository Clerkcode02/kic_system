<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Booking\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'scheduled_date' => $this->scheduled_date->toDateString(),
            'time_slot_start' => $this->time_slot_start,
            'time_slot_end' => $this->time_slot_end,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'service' => [
                'id' => $this->service->id,
                'title' => $this->service->title,
                'pricing_type' => $this->service->pricing_type,
            ],
            'provider' => [
                'id' => $this->provider->id,
                'legal_name' => $this->provider->legal_name,
            ],
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
