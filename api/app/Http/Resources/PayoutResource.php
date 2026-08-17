<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\Payment\Models\Payout
 */
class PayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->provider_id,
            'provider_name' => $this->whenLoaded('provider', fn () => $this->provider->legal_name),
            'amount' => $this->amount->toDecimal(),
            'currency' => $this->currency,
            'status' => $this->status,
            'stripe_transfer_id' => $this->stripe_transfer_id,
            'created_at' => $this->created_at,
        ];
    }
}
