<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Project;

use App\Domain\Freelance\Actions\CancelProject;
use App\Domain\Freelance\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\CancelProjectRequest;
use App\Http\Resources\ProjectResource;

class CancelProjectController extends Controller
{
    public function __invoke(CancelProjectRequest $request, Project $project, CancelProject $action): ProjectResource
    {
        $project = $action->handle($project, $request->user());

        return new ProjectResource($project->load(['category', 'client']));
    }
}
