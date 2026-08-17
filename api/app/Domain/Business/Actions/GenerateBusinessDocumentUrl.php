<?php

declare(strict_types=1);

namespace App\Domain\Business\Actions;

use App\Domain\Business\Models\BusinessDocument;
use App\Support\Action;
use Illuminate\Support\Facades\Storage;

/**
 * CLAUDE.md §16: private bucket, short-TTL signed URL per request — mirrors
 * GenerateAttachmentDownloadUrl for the (non-Attachment) verification
 * document model used by the admin verification queue's document viewer.
 */
final class GenerateBusinessDocumentUrl implements Action
{
    private const TTL_MINUTES = 10;

    /**
     * @return array{url: string, expires_at: string}
     */
    public function handle(BusinessDocument $document): array
    {
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        $url = Storage::disk('s3')->temporaryUrl($document->file_path, $expiresAt);

        return [
            'url' => $url,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }
}
