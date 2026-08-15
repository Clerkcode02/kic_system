<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Review;

use App\Domain\Review\Actions\ReplyToReview;
use App\Domain\Review\Models\Review;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\ReplyToReviewRequest;
use App\Http\Resources\ReviewResource;

class ReplyToReviewController extends Controller
{
    public function __invoke(ReplyToReviewRequest $request, Review $review, ReplyToReview $action): ReviewResource
    {
        return new ReviewResource($action->handle($review, $request->validated()['reply']));
    }
}
