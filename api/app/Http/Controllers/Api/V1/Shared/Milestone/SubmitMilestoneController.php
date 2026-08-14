<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Milestone;

use App\Domain\Freelance\Actions\SubmitMilestone;
use App\Domain\Freelance\Models\Milestone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Milestone\SubmitMilestoneRequest;
use App\Http\Resources\MilestoneResource;

class SubmitMilestoneController extends Controller
{
    public function __invoke(SubmitMilestoneRequest $request, Milestone $milestone, SubmitMilestone $action): MilestoneResource
    {
        $milestone = $action->handle($milestone, $request->user(), $request->validated()['deliverable_ids']);

        return new MilestoneResource($milestone);
    }
}
