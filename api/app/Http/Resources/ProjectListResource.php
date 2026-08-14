<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Freelance\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'budget_min' => $this->budget_min->toDecimal(),
            'budget_max' => $this->budget_max->toDecimal(),
            'currency' => $this->currency,
            'deadline' => $this->deadline->toDateString(),
            'status' => $this->status,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
