<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Business;

use App\Domain\Business\Actions\ConfirmBusinessDocument;
use App\Domain\Business\Actions\RequestDocumentUploadUrl;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\Business\ConfirmBusinessDocumentRequest;
use App\Http\Requests\Provider\Business\RequestDocumentUploadUrlRequest;
use App\Http\Resources\BusinessDocumentResource;
use Illuminate\Http\JsonResponse;

class BusinessDocumentController extends Controller
{
    public function uploadUrl(RequestDocumentUploadUrlRequest $request, RequestDocumentUploadUrl $action): JsonResponse
    {
        $business = $request->user()->business;

        abort_if($business === null, 403);

        $validated = $request->validated();

        return response()->json([
            'data' => $action->handle($business, $validated['filename'], $validated['document_type']),
        ]);
    }

    public function store(ConfirmBusinessDocumentRequest $request, ConfirmBusinessDocument $action): BusinessDocumentResource
    {
        $business = $request->user()->business;

        abort_if($business === null, 403);

        return new BusinessDocumentResource($action->handle($business, $request->validated()));
    }
}
