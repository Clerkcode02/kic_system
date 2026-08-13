<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Catalog;

use App\Domain\Catalog\Actions\DeactivateService;
use App\Domain\Catalog\Actions\PublishService;
use App\Domain\Catalog\Actions\UpdateService;
use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Queries\ProviderServiceListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\Catalog\DestroyServiceRequest;
use App\Http\Requests\Provider\Catalog\ProviderServiceIndexRequest;
use App\Http\Requests\Provider\Catalog\ShowServiceRequest;
use App\Http\Requests\Provider\Catalog\StoreServiceRequest;
use App\Http\Requests\Provider\Catalog\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ServiceController extends Controller
{
    public function index(ProviderServiceIndexRequest $request, ProviderServiceListQuery $query): AnonymousResourceCollection
    {
        $business = $request->user()->business;

        abort_if($business === null, 403);

        return ServiceResource::collection($query->handle($business));
    }

    public function show(ShowServiceRequest $request, Service $service): ServiceResource
    {
        return new ServiceResource($service->load('pricingTiers', 'category'));
    }

    public function store(StoreServiceRequest $request, PublishService $action): ServiceResource
    {
        $business = $request->user()->business;

        // FormRequest::authorize() already rejected a request with no
        // business before validation ran — this narrows the type for static
        // analysis, not a reachable runtime path.
        abort_if($business === null, 403);

        return new ServiceResource($action->handle($business, $request->validated()));
    }

    public function update(UpdateServiceRequest $request, Service $service, UpdateService $action): ServiceResource
    {
        return new ServiceResource($action->handle($service, $request->validated()));
    }

    public function destroy(DestroyServiceRequest $request, Service $service, DeactivateService $action): Response
    {
        $action->handle($service);

        return response()->noContent();
    }
}
