<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Freelancer\Proposal;

use App\Domain\Freelance\Actions\WithdrawProposal;
use App\Domain\Freelance\Models\Proposal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposal\WithdrawProposalRequest;
use App\Http\Resources\ProposalResource;

class WithdrawProposalController extends Controller
{
    public function __invoke(WithdrawProposalRequest $request, Proposal $proposal, WithdrawProposal $action): ProposalResource
    {
        $proposal = $action->handle($proposal, $request->user());

        return new ProposalResource($proposal->load('freelancer.user'));
    }
}
