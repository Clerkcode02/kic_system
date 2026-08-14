<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\User\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 */
class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'street' => $this->street,
            'unit' => $this->unit,
            'city' => $this->city,
            'state_province' => $this->state_province,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
            'is_default' => $this->is_default,
        ];
    }
}
