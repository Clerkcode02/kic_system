<?php

declare(strict_types=1);

namespace App\Domain\Business\Actions;

use App\Domain\Business\Models\Business;
use App\Support\Action;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RequestDocumentUploadUrl implements Action
{
    private const TTL_MINUTES = 10;

    /**
     * @return array{path: string, url: string, headers: array<string, string>, expires_at: string}
     */
    public function handle(Business $business, string $filename, string $documentType): array
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $path = sprintf(
            'business-documents/%s/%s-%s.%s',
            $business->id,
            $documentType,
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
