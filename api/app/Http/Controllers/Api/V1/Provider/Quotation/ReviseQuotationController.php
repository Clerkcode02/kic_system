<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Quotation;

use App\Domain\Quotation\Actions\ReviseQuotation;
use App\Domain\Quotation\Models\Quotation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\ReviseQuotationRequest;
use App\Http\Resources\QuotationResource;
use Illuminate\Http\JsonResponse;

class ReviseQuotationController extends Controller
{
    public function __invoke(ReviseQuotationRequest $request, Quotation $quotation, ReviseQuotation $action): JsonResponse
    {
        $revised = $action->handle($quotation, $request->user(), $request->validated());

        return (new QuotationResource($revised))
            ->response()
            ->setStatusCode(201);
    }
}
