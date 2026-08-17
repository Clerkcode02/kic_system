<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Dispute\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dispute
 */
class DisputeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'disputable_type' => $this->disputable_type,
            'disputable_id' => $this->disputable_id,
            'raised_by' => $this->raised_by,
            'assigned_admin_id' => $this->assigned_admin_id,
            'status' => $this->status->value,
            'resolution_notes' => $this->resolution_notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
