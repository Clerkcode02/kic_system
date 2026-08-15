<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Attachment;

use App\Domain\Platform\Actions\GenerateAttachmentDownloadUrl;
use App\Domain\Platform\Models\Attachment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    public function url(Request $request, Attachment $attachment, GenerateAttachmentDownloadUrl $action): JsonResponse
    {
        abort_unless($request->user()?->can('manageEvidence', $attachment->attachable), 403);

        // CLAUDE.md §16: unscanned files are never served, regardless of
        // who's authorized to view the parent resource.
        abort_unless($attachment->scanned, 404);

        return response()->json(['data' => $action->handle($attachment)]);
    }
}
