<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Freelance\Models\Deliverable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * CLAUDE.md §16: "until scanned, files are not served" — `download_url` is
 * only populated once the queued virus scan has cleared the file.
 *
 * @mixin Deliverable
 */
class DeliverableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'milestone_id' => $this->milestone_id,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'description' => $this->description,
            'submitted_at' => $this->submitted_at,
            'scanned' => $this->scanned,
            'download_url' => $this->scanned
                ? Storage::disk('s3')->temporaryUrl($this->file_path, now()->addMinutes(10))
                : null,
        ];
    }
}
