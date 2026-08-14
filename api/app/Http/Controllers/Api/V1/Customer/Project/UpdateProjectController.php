<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Project;

use App\Domain\Freelance\Actions\UpdateProject;
use App\Domain\Freelance\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;

class UpdateProjectController extends Controller
{
    public function __invoke(UpdateProjectRequest $request, Project $project, UpdateProject $action): ProjectResource
    {
        $project = $action->handle($project, $request->user(), $request->validated());

        return new ProjectResource($project->load(['category', 'client']));
    }
}
