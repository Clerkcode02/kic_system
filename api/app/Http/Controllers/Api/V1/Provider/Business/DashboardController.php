<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Business;

use App\Domain\Business\Queries\ProviderDashboardSummaryQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderDashboardResource;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, ProviderDashboardSummaryQuery $query): ProviderDashboardResource
    {
        $business = $request->user()->business;

        abort_if($business === null, 404);

        return new ProviderDashboardResource(collect($query->handle($business)));
    }
}
