<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Quotation;

use App\Domain\Booking\Models\Booking;
use App\Domain\Quotation\Actions\SendQuotation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\StoreQuotationRequest;
use App\Http\Resources\QuotationResource;
use Illuminate\Http\JsonResponse;

class StoreQuotationController extends Controller
{
    public function __invoke(StoreQuotationRequest $request, Booking $booking, SendQuotation $action): JsonResponse
    {
        $quotation = $action->handle($booking, $request->user(), $request->validated());

        return (new QuotationResource($quotation))
            ->response()
            ->setStatusCode(201);
    }
}
