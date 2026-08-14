<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Freelancer\Proposal;

use App\Domain\Freelance\Queries\ListMyProposalsQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProposalListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListMyProposalsController extends Controller
{
    public function __invoke(Request $request, ListMyProposalsQuery $query): AnonymousResourceCollection
    {
        return ProposalListResource::collection($query->handle($request->user(), $request->only('cursor')));
    }
}
