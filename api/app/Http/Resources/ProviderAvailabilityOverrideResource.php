<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Business\Models\ProviderAvailabilityOverride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderAvailabilityOverride
 */
class ProviderAvailabilityOverrideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'is_blackout' => $this->is_blackout,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
        ];
    }
}
