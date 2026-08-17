<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\Business\Models\Business
 */
class BusinessVerificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_name' => $this->legal_name,
            'registration_number' => $this->registration_number,
            'verification_status' => $this->verification_status,
            'city' => $this->city,
            'province' => $this->province,
            'owner' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($document) => [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'issuing_authority' => $document->issuing_authority,
                'issued_at' => $document->issued_at,
                'expires_at' => $document->expires_at,
                'verification_status' => $document->verification_status,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
