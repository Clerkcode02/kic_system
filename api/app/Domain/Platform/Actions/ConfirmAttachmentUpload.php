<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Jobs\GenerateImageVariantsJob;
use App\Domain\Platform\Jobs\ScanAttachmentJob;
use App\Domain\Platform\Models\Attachment;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use Illuminate\Database\Eloquent\Model;

/**
 * Second half of the presigned upload flow (request URL → direct upload →
 * confirm here → queued virus scan). CLAUDE.md §16: files are never served
 * until scanned; image variants are generated async, not on the request
 * path.
 */
final class ConfirmAttachmentUpload implements Action
{
    /**
     * @param  array{attachable_type: string, file_path: string, mime_type: string, size_bytes: int}  $data
     */
    public function handle(Model $attachable, User $actor, array $data): Attachment
    {
        $expectedPrefix = sprintf('attachments/%s/%s/', $data['attachable_type'], $attachable->getKey());

        if (! str_starts_with($data['file_path'], $expectedPrefix)) {
            throw new ConflictException('The uploaded file does not belong to this resource.', 'invalid_upload_path');
        }

        $attachment = Attachment::create([
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'uploaded_by' => $actor->id,
            'disk' => 's3',
            'path' => $data['file_path'],
            'mime_type' => $data['mime_type'],
            'size_bytes' => $data['size_bytes'],
            'scanned' => false,
        ]);

        ScanAttachmentJob::dispatch($attachment);

        if (str_starts_with($attachment->mime_type, 'image/')) {
            GenerateImageVariantsJob::dispatch($attachment);
        }

        return $attachment;
    }
}
