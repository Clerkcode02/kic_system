<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Review;

use App\Domain\Freelance\Actions\SubmitProjectReview;
use App\Domain\Freelance\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreProjectReviewRequest;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\JsonResponse;

class ProjectReviewController extends Controller
{
    public function __invoke(StoreProjectReviewRequest $request, Project $project, SubmitProjectReview $action): JsonResponse
    {
        $validated = $request->validated();

        $review = $action->handle($project, $request->user(), (int) $validated['rating'], $validated['comment'] ?? null);

        return (new ReviewResource($review))
            ->response()
            ->setStatusCode(201);
    }
}
