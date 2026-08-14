<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Quotation;

use App\Domain\Quotation\Models\Quotation;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuotationResource;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function show(Request $request, Quotation $quotation): QuotationResource
    {
        abort_unless($request->user()?->can('view', $quotation), 403);

        return new QuotationResource($quotation->load('lineItems'));
    }
}
