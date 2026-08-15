<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Models\Project;
use App\Domain\Review\Actions\SubmitReview;
use App\Domain\Review\Models\Review;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;

/**
 * SRS §19: reviews are only after completion, one per completed
 * transaction. This Action owns those two checks for projects before
 * delegating to the generic SubmitReview write path. The reviewee is the
 * freelancer hired on the project's (single, exclusive) contract.
 */
final class SubmitProjectReview implements Action
{
    public function __construct(private readonly SubmitReview $submitReview)
    {
    }

    public function handle(Project $project, User $reviewer, int $rating, ?string $comment): Review
    {
        if ($project->status !== ProjectStatus::Completed) {
            throw new ConflictException('Reviews can only be left after the project is completed.', 'project_not_completed');
        }

        if ($project->reviews()->where('reviewer_id', $reviewer->id)->exists()) {
            throw new ConflictException('You have already reviewed this project.', 'duplicate_review');
        }

        $freelancerUser = $project->contract?->proposal?->freelancer?->user;

        if ($freelancerUser === null) {
            throw new ConflictException('This project has no hired freelancer to review.', 'no_hired_freelancer');
        }

        return $this->submitReview->handle($reviewer, $freelancerUser, $project, $rating, $comment);
    }
}
