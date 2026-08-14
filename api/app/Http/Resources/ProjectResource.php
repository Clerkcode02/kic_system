<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Freelance\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'budget_min' => $this->budget_min->toDecimal(),
            'budget_max' => $this->budget_max->toDecimal(),
            'currency' => $this->currency,
            'deadline' => $this->deadline->toDateString(),
            'status' => $this->status,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ],
            'client' => [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ],
            'contract' => $this->whenLoaded('contract', fn () => $this->contract !== null ? new ContractResource($this->contract) : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
