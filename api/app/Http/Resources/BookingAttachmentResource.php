<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Booking\Models\BookingAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingAttachment
 */
class BookingAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uploaded_by' => $this->uploaded_by,
            'file_path' => $this->file_path,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'caption' => $this->caption,
            'created_at' => $this->created_at,
        ];
    }
}
