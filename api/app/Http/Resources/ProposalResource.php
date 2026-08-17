<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Freelance\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Proposal
 */
class ProposalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'proposed_amount' => $this->proposed_amount->toDecimal(),
            'currency' => $this->currency,
            'cover_letter' => $this->cover_letter,
            'delivery_days' => $this->delivery_days,
            'status' => $this->status,
            'freelancer' => [
                'id' => $this->freelancer->id,
                'user_id' => $this->freelancer->user_id,
                'headline' => $this->freelancer->headline,
                'rating_avg' => (float) $this->freelancer->rating_avg,
                'name' => $this->freelancer->relationLoaded('user') ? $this->freelancer->user->name : null,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
