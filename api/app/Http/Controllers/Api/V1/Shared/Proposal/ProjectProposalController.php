<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Proposal;

use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Queries\ListProposalsForProjectQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposal\IndexProposalRequest;
use App\Http\Resources\ProposalResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectProposalController extends Controller
{
    public function __invoke(IndexProposalRequest $request, Project $project, ListProposalsForProjectQuery $query): AnonymousResourceCollection
    {
        return ProposalResource::collection($query->handle($project->id, $request->validated()));
    }
}
