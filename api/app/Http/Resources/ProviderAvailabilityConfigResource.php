<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Business\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Business
 */
class ProviderAvailabilityConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'weekly' => ProviderAvailabilityResource::collection($this->whenLoaded('availability')),
            'overrides' => ProviderAvailabilityOverrideResource::collection($this->whenLoaded('availabilityOverrides')),
        ];
    }
}
