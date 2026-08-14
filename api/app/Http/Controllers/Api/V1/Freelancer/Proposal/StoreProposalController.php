<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Freelancer\Proposal;

use App\Domain\Freelance\Actions\SubmitProposal;
use App\Domain\Freelance\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposal\StoreProposalRequest;
use App\Http\Resources\ProposalResource;
use Illuminate\Http\JsonResponse;

class StoreProposalController extends Controller
{
    public function __invoke(StoreProposalRequest $request, Project $project, SubmitProposal $action): JsonResponse
    {
        $proposal = $action->handle($project, $request->user(), $request->validated());

        return (new ProposalResource($proposal))
            ->response()
            ->setStatusCode(201);
    }
}
