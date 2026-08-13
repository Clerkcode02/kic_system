<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Catalog;

use App\Domain\Catalog\Actions\PublishService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\Catalog\StoreServiceRequest;
use App\Http\Resources\ServiceResource;

class ServiceController extends Controller
{
    public function store(StoreServiceRequest $request, PublishService $action): ServiceResource
    {
        $business = $request->user()->business;

        // FormRequest::authorize() already rejected a request with no
        // business before validation ran — this narrows the type for static
        // analysis, not a reachable runtime path.
        abort_if($business === null, 403);

        return new ServiceResource($action->handle($business, $request->validated()));
    }
}
