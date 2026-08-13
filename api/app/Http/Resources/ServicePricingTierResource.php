<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Catalog\Models\ServicePricingTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServicePricingTier
 */
class ServicePricingTierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tier_name' => $this->tier_name,
            'description' => $this->description,
            'price' => $this->price->toDecimal(),
            'currency' => $this->currency,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'sort_order' => $this->sort_order,
        ];
    }
}
