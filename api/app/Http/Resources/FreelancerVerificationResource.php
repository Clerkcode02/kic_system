<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\Freelance\Models\FreelancerProfile
 */
class FreelancerVerificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'years_experience' => $this->years_experience,
            'approval_status' => $this->approval_status,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->pluck('skill_name')),
            'portfolio_items' => $this->whenLoaded('portfolioItems', fn () => $this->portfolioItems->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'project_url' => $item->project_url,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
