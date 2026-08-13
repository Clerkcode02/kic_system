<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Catalog\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Estimated pricing before booking — server-computed from stored source
 * rows only, never a client-supplied figure.
 *
 * @mixin Service
 */
class ServicePricingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'service_id' => $this->id,
            'pricing_type' => $this->pricing_type,
            'base_price' => $this->base_price->toDecimal(),
            'currency' => $this->currency,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'pricing_tiers' => ServicePricingTierResource::collection($this->whenLoaded('pricingTiers')),
        ];
    }
}
