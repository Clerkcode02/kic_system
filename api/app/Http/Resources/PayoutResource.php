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
            'amount' => $this->amount->toDecimal(),
            'currency' => $this->currency,
            'stripe_transfer_id' => $this->stripe_transfer_id,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
