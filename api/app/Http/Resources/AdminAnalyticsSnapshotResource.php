<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\Reporting\Models\AdminAnalyticsSnapshot
 */
class AdminAnalyticsSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'snapshot_at' => $this->snapshot_at,
            'metrics' => $this->metrics,
        ];
    }
}
