<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Project;

use App\Domain\Freelance\Queries\ListProjectsQuery;
use App\Domain\Freelance\Queries\ProjectDetailQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\IndexProjectRequest;
use App\Http\Resources\ProjectListResource;
use App\Http\Resources\ProjectResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function index(IndexProjectRequest $request, ListProjectsQuery $query): AnonymousResourceCollection
    {
        return ProjectListResource::collection($query->handle($request->validated()));
    }

    public function show(string $project, ProjectDetailQuery $query): ProjectResource
    {
        return new ProjectResource($query->handle($project));
    }
}
