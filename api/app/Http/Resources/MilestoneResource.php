<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Freelance\Models\Milestone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Milestone
 */
class MilestoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'title' => $this->title,
            'amount' => $this->amount->toDecimal(),
            'currency' => $this->currency,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
