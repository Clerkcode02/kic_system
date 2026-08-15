<?php

declare(strict_types=1);

namespace App\Domain\Platform\Jobs;

use App\Domain\Platform\Models\Attachment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * CLAUDE.md §16: thumbnail generation happens async, off the request path.
 * Only dispatched for image attachments (see ConfirmAttachmentUpload).
 */
class GenerateImageVariantsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const THUMBNAIL_WIDTH = 400;

    public function __construct(public readonly Attachment $attachment)
    {
    }

    public function handle(): void
    {
        $disk = Storage::disk($this->attachment->disk);

        $manager = new ImageManager(new Driver());
        $image = $manager->decodeBinary($disk->get($this->attachment->path));
        $image->scaleDown(width: self::THUMBNAIL_WIDTH);

        $thumbnailPath = preg_replace(
            '/(\.[^.\/]+)$/',
            '-thumb$1',
            $this->attachment->path
        ) ?? $this->attachment->path.'-thumb';

        $disk->put($thumbnailPath, (string) $image->encode());

        $this->attachment->update([
            'variants' => ['thumbnail' => $thumbnailPath],
        ]);
    }
}
