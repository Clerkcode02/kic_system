<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Freelance\Models\Milestone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\Payment\Models\Payment
 */
class FreelancerEarningResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'milestone_id' => $this->payable_id,
            'milestone_title' => $this->whenLoaded(
                'payable',
                fn () => $this->payable instanceof Milestone ? $this->payable->title : null,
            ),
            'amount' => $this->amount->toDecimal(),
            'platform_fee_amount' => $this->platform_fee_amount->toDecimal(),
            'net_amount' => $this->provider_net_amount->toDecimal(),
            'currency' => $this->currency,
            'stripe_transfer_id' => $this->stripe_transfer_id,
            'released' => $this->stripe_transfer_id !== null,
            'created_at' => $this->created_at,
        ];
    }
}
