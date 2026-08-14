<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Models\Milestone;
use App\Support\Action;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CLAUDE.md §16: presigned direct-to-S3 upload, mirroring
 * RequestDocumentUploadUrl — the file never transits the app server.
 */
final class RequestDeliverableUploadUrl implements Action
{
    private const TTL_MINUTES = 10;

    /**
     * @return array{path: string, url: string, headers: array<string, string>, expires_at: string}
     */
    public function handle(Milestone $milestone, string $filename): array
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $path = sprintf(
            'deliverables/%s/%s.%s',
            $milestone->id,
            (string) Str::uuid7(),
            $extension !== '' ? $extension : 'bin'
        );

        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        /** @var array{url: string, headers: array<string, string>} $presigned */
        $presigned = Storage::disk('s3')->temporaryUploadUrl($path, $expiresAt);

        return [
            'path' => $path,
            'url' => $presigned['url'],
            'headers' => $presigned['headers'],
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }
}
