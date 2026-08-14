<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Freelance\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Proposal
 */
class ProposalListResource extends JsonResource
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
            'delivery_days' => $this->delivery_days,
            'status' => $this->status,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'title' => $this->project->title,
                'status' => $this->project->status,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
