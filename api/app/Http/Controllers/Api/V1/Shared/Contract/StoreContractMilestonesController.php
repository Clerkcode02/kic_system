<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Contract;

use App\Domain\Freelance\Actions\CreateContractMilestones;
use App\Domain\Freelance\Models\Contract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreContractMilestonesRequest;
use App\Http\Resources\MilestoneResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StoreContractMilestonesController extends Controller
{
    public function __invoke(StoreContractMilestonesRequest $request, Contract $contract, CreateContractMilestones $action): AnonymousResourceCollection
    {
        $milestones = $action->handle($contract, $request->validated()['milestones'], $request->user());

        return MilestoneResource::collection($milestones);
    }
}
