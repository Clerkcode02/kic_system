<?php

declare(strict_types=1);

namespace App\Domain\Business\Actions;

use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessDocument;
use App\Support\Action;
use App\Support\ConflictException;

class ConfirmBusinessDocument implements Action
{
    /**
     * @param  array{
     *     file_path: string, document_type: string, document_number: string,
     *     issuing_authority?: ?string, issued_at?: ?string, expires_at?: ?string,
     * }  $data
     */
    public function handle(Business $business, array $data): BusinessDocument
    {
        if (! str_starts_with($data['file_path'], "business-documents/{$business->id}/")) {
            throw new ConflictException('The uploaded file does not belong to this business.', 'invalid_upload_path');
        }

        return BusinessDocument::create([
            'business_id' => $business->id,
            'document_type' => $data['document_type'],
            'document_number' => $data['document_number'],
            'file_path' => $data['file_path'],
            'issuing_authority' => $data['issuing_authority'] ?? null,
            'issued_at' => $data['issued_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'verification_status' => BusinessVerificationStatus::Pending,
        ]);
    }
}
