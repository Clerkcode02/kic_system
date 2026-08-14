<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Milestone;

use App\Domain\Freelance\Actions\RejectMilestone;
use App\Domain\Freelance\Models\Milestone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Milestone\RejectMilestoneRequest;
use App\Http\Resources\MilestoneResource;

class RejectMilestoneController extends Controller
{
    public function __invoke(RejectMilestoneRequest $request, Milestone $milestone, RejectMilestone $action): MilestoneResource
    {
        $milestone = $action->handle($milestone, $request->user(), $request->validated()['reason']);

        return new MilestoneResource($milestone);
    }
}
