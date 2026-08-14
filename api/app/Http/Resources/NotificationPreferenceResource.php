<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\Notification\Models\NotificationPreference
 */
class NotificationPreferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'category' => $this->category,
            'in_app' => true,
            'email' => $this->email,
            'push_web' => $this->push_web,
        ];
    }
}
