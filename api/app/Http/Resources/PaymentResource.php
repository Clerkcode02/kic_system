<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\Payment\Models\Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payable_type' => $this->payable_type,
            'payable_id' => $this->payable_id,
            'stripe_payment_intent_id' => $this->stripe_payment_intent_id,
            'amount' => $this->amount->toDecimal(),
            'platform_fee_amount' => $this->platform_fee_amount->toDecimal(),
            'provider_net_amount' => $this->provider_net_amount->toDecimal(),
            'currency' => $this->currency,
            'type' => $this->type,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
