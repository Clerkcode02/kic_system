<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Models\PortfolioItem;
use App\Support\Action;
use Illuminate\Support\Facades\Storage;

/**
 * CLAUDE.md §16: private bucket, short-TTL signed URL per request — mirrors
 * GenerateAttachmentDownloadUrl for the admin verification queue's
 * freelancer portfolio viewer.
 */
final class GeneratePortfolioItemUrl implements Action
{
    private const TTL_MINUTES = 10;

    /**
     * @return array{url: string, expires_at: string}
     */
    public function handle(PortfolioItem $portfolioItem): array
    {
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        $url = Storage::disk('s3')->temporaryUrl($portfolioItem->file_path, $expiresAt);

        return [
            'url' => $url,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }
}
