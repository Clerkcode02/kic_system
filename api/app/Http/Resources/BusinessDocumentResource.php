<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Business\Models\BusinessDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessDocument
 */
class BusinessDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'file_path' => $this->file_path,
            'issuing_authority' => $this->issuing_authority,
            'issued_at' => $this->issued_at,
            'expires_at' => $this->expires_at,
            'verification_status' => $this->verification_status,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
        ];
    }
}
