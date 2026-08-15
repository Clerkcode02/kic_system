<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Freelancer\Contract;

use App\Domain\Freelance\Queries\ListMyContractsQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContractResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContractController extends Controller
{
    public function index(Request $request, ListMyContractsQuery $query): AnonymousResourceCollection
    {
        return ContractResource::collection($query->handle($request->user(), $request->only('cursor')));
    }
}
