<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Milestone;

use App\Domain\Freelance\Actions\ApproveMilestone;
use App\Domain\Freelance\Models\Milestone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Milestone\ApproveMilestoneRequest;
use App\Http\Resources\MilestoneResource;

class ApproveMilestoneController extends Controller
{
    public function __invoke(ApproveMilestoneRequest $request, Milestone $milestone, ApproveMilestone $action): MilestoneResource
    {
        $milestone = $action->handle($milestone, $request->user());

        return new MilestoneResource($milestone);
    }
}
