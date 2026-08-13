<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Catalog;

use App\Domain\Catalog\Queries\ServiceDetailQuery;
use App\Domain\Catalog\Queries\ServiceListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ServiceIndexRequest;
use App\Http\Resources\ServiceListResource;
use App\Http\Resources\ServicePricingResource;
use App\Http\Resources\ServiceResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    public function index(ServiceIndexRequest $request, ServiceListQuery $query): AnonymousResourceCollection
    {
        return ServiceListResource::collection($query->handle($request->validated()));
    }

    public function show(string $service, ServiceDetailQuery $query): ServiceResource
    {
        return new ServiceResource($query->handle($service));
    }

    public function pricing(string $service, ServiceDetailQuery $query): ServicePricingResource
    {
        return new ServicePricingResource($query->handle($service));
    }
}
