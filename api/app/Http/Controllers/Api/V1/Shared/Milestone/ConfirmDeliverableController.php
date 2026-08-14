<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Milestone;

use App\Domain\Freelance\Actions\ConfirmDeliverable;
use App\Domain\Freelance\Models\Milestone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Milestone\ConfirmDeliverableRequest;
use App\Http\Resources\DeliverableResource;

class ConfirmDeliverableController extends Controller
{
    public function __invoke(ConfirmDeliverableRequest $request, Milestone $milestone, ConfirmDeliverable $action): DeliverableResource
    {
        $deliverable = $action->handle($milestone, $request->user(), $request->validated());

        return new DeliverableResource($deliverable);
    }
}
