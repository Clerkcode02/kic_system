<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Upload;

use App\Domain\Platform\Actions\ConfirmAttachmentUpload;
use App\Domain\Platform\Actions\RequestAttachmentUploadUrl;
use App\Http\Controllers\Controller;
use App\Http\Requests\Upload\ConfirmUploadRequest;
use App\Http\Requests\Upload\RequestUploadUrlRequest;
use App\Http\Resources\AttachmentResource;
use App\Support\MorphResolver;
use Illuminate\Http\JsonResponse;

class UploadController extends Controller
{
    public function presign(RequestUploadUrlRequest $request, RequestAttachmentUploadUrl $action): JsonResponse
    {
        $validated = $request->validated();

        $attachable = MorphResolver::resolve($validated['attachable_type'], $validated['attachable_id']);

        abort_if($attachable === null, 404);

        return response()->json([
            'data' => $action->handle($attachable, $validated['attachable_type'], $validated['filename']),
        ]);
    }

    public function confirm(ConfirmUploadRequest $request, ConfirmAttachmentUpload $action): AttachmentResource
    {
        $validated = $request->validated();

        $attachable = MorphResolver::resolve($validated['attachable_type'], $validated['attachable_id']);

        abort_if($attachable === null, 404);

        return new AttachmentResource($action->handle($attachable, $request->user(), $validated));
    }
}
