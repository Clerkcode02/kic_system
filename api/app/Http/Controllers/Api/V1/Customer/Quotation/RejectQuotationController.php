<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Quotation;

use App\Domain\Quotation\Actions\RejectQuotation;
use App\Domain\Quotation\Models\Quotation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\RejectQuotationRequest;
use App\Http\Resources\QuotationResource;

class RejectQuotationController extends Controller
{
    public function __invoke(RejectQuotationRequest $request, Quotation $quotation, RejectQuotation $action): QuotationResource
    {
        $rejected = $action->handle($quotation, $request->user(), $request->validated('reason'));

        return new QuotationResource($rejected);
    }
}
