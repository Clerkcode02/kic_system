<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Milestone;

use App\Domain\Freelance\Actions\RequestDeliverableUploadUrl;
use App\Domain\Freelance\Models\Milestone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Milestone\RequestDeliverableUploadUrlRequest;
use Illuminate\Http\JsonResponse;

class RequestDeliverableUploadUrlController extends Controller
{
    public function __invoke(RequestDeliverableUploadUrlRequest $request, Milestone $milestone, RequestDeliverableUploadUrl $action): JsonResponse
    {
        return response()->json([
            'data' => $action->handle($milestone, $request->validated()['filename']),
        ]);
    }
}
