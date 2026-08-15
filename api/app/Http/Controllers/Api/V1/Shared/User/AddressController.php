<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\User;

use App\Domain\User\Actions\StoreAddress;
use App\Domain\User\Queries\ListMyAddressesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreAddressRequest;
use App\Http\Resources\AddressResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends Controller
{
    public function index(Request $request, ListMyAddressesQuery $query): AnonymousResourceCollection
    {
        return AddressResource::collection($query->handle($request->user()));
    }

    public function store(StoreAddressRequest $request, StoreAddress $action): JsonResponse
    {
        $address = $action->handle($request->user(), $request->validated());

        return (new AddressResource($address))
            ->response()
            ->setStatusCode(201);
    }
}
