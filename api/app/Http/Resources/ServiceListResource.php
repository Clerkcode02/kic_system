<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Catalog\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 */
class ServiceListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'pricing_type' => $this->pricing_type,
            'base_price' => $this->base_price->toDecimal(),
            'currency' => $this->currency,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ],
            'business' => [
                'id' => $this->business->id,
                'legal_name' => $this->business->legal_name,
                'rating_avg' => $this->business->rating_avg,
            ],
        ];
    }
}
