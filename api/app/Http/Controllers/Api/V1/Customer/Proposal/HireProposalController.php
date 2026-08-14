<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Proposal;

use App\Domain\Freelance\Actions\HireFreelancer;
use App\Domain\Freelance\Models\Proposal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposal\HireProposalRequest;
use App\Http\Resources\ContractResource;

class HireProposalController extends Controller
{
    public function __invoke(HireProposalRequest $request, Proposal $proposal, HireFreelancer $action): ContractResource
    {
        $contract = $action->handle($proposal, $request->user());

        return new ContractResource($contract);
    }
}
