<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Contract;

use App\Domain\Freelance\Queries\ListMyContractsQuery;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\IndexMyContractRequest;
use App\Http\Resources\ContractResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Shared rather than role-scoped: a contract's client and freelancer both
 * need a list of the contracts they're party to, and ListMyContractsQuery
 * resolves which side the caller is on.
 */
class MyContractController extends Controller
{
    public function __invoke(IndexMyContractRequest $request, ListMyContractsQuery $query): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return ContractResource::collection($query->handle($user, $request->validated()));
    }
}
