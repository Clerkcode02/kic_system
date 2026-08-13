<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Business\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Business
 */
class BusinessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_name' => $this->legal_name,
            'registration_number' => $this->registration_number,
            'verification_status' => $this->verification_status,
            'business_hours' => $this->business_hours,
            'rating_avg' => (float) $this->rating_avg,
            'max_bookings_per_day' => $this->max_bookings_per_day,
            'address' => [
                'street' => $this->street,
                'unit' => $this->unit,
                'city' => $this->city,
                'province' => $this->province,
                'postal_code' => $this->postal_code,
            ],
            'location' => $this->resource->locationPoint(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
