<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\Attachment;
use App\Support\Action;
use Illuminate\Support\Facades\Storage;

/**
 * CLAUDE.md §16: private bucket, downloads proxied through a signed,
 * short-TTL URL generated per request. Whether the attachment is scanned
 * yet is checked by the caller (404 until scanned — unscanned files are
 * never served).
 */
final class GenerateAttachmentDownloadUrl implements Action
{
    private const TTL_MINUTES = 10;

    /**
     * @return array{url: string, expires_at: string}
     */
    public function handle(Attachment $attachment): array
    {
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        $url = Storage::disk($attachment->disk)->temporaryUrl($attachment->path, $expiresAt);

        return [
            'url' => $url,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }
}
