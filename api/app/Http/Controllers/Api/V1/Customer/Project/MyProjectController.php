<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Project;

use App\Domain\Freelance\Queries\ListMyProjectsQuery;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\IndexMyProjectRequest;
use App\Http\Resources\ProjectListResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MyProjectController extends Controller
{
    public function __invoke(IndexMyProjectRequest $request, ListMyProjectsQuery $query): AnonymousResourceCollection
    {
        /** @var User $client */
        $client = $request->user();

        return ProjectListResource::collection($query->handle($client, $request->validated()));
    }
}
