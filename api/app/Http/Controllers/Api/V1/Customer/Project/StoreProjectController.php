<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Project;

use App\Domain\Freelance\Actions\PublishProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use Illuminate\Http\JsonResponse;

class StoreProjectController extends Controller
{
    public function __invoke(StoreProjectRequest $request, PublishProject $action): JsonResponse
    {
        $project = $action->handle($request->user(), $request->validated());

        return (new ProjectResource($project->load(['category', 'client'])))
            ->response()
            ->setStatusCode(201);
    }
}
