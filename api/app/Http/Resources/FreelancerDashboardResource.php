<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Illuminate\Support\Collection<string, mixed>
 */
class FreelancerDashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'open_proposal_count' => $this->resource['open_proposal_count'],
            'active_contract_count' => $this->resource['active_contract_count'],
            'attention_milestones' => MilestoneResource::collection($this->resource['attention_milestones']),
            'earnings' => [
                'total' => $this->resource['earnings_total']->toDecimal(),
                'currency' => 'CAD',
            ],
        ];
    }
}
