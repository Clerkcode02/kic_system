<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Freelance\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contract
 */
class ContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'proposal_id' => $this->proposal_id,
            'total_amount' => $this->total_amount->toDecimal(),
            'currency' => $this->currency,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'title' => $this->project->title,
                'status' => $this->project->status,
            ]),
            'milestones' => MilestoneResource::collection($this->whenLoaded('milestones')),
        ];
    }
}
