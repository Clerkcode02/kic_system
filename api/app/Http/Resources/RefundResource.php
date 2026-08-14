<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\Payment\Models\Refund
 */
class RefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'stripe_refund_id' => $this->stripe_refund_id,
            'amount' => $this->amount->toDecimal(),
            'currency' => $this->currency,
            'reason' => $this->reason,
            'status' => $this->status,
            'initiated_by' => $this->initiated_by,
            'created_at' => $this->created_at,
        ];
    }
}
