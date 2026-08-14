<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Business;

use App\Domain\Payment\Queries\ListProviderEarningsQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\PayoutResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EarningsController extends Controller
{
    public function index(Request $request, ListProviderEarningsQuery $query): AnonymousResourceCollection
    {
        $business = $request->user()->business;

        abort_if($business === null, 404);

        return PayoutResource::collection($query->handle($business));
    }
}
