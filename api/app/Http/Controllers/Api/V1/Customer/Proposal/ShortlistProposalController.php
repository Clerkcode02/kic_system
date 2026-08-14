<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Proposal;

use App\Domain\Freelance\Actions\ShortlistProposal;
use App\Domain\Freelance\Models\Proposal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposal\ShortlistProposalRequest;
use App\Http\Resources\ProposalResource;

class ShortlistProposalController extends Controller
{
    public function __invoke(ShortlistProposalRequest $request, Proposal $proposal, ShortlistProposal $action): ProposalResource
    {
        $proposal = $action->handle($proposal, $request->user());

        return new ProposalResource($proposal->load('freelancer.user'));
    }
}
