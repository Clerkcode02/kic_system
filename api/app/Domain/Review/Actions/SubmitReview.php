<?php

declare(strict_types=1);

namespace App\Domain\Review\Actions;

use App\Domain\Platform\Services\SettingsRepository;
use App\Domain\Review\Events\ReviewReceived;
use App\Domain\Review\Jobs\SyncBusinessRatingAverageJob;
use App\Domain\Review\Models\Review;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use Illuminate\Database\Eloquent\Model;

/**
 * SRS §6 — post-completion only, one per completed transaction, no
 * self-review. Kept minimal: the reviewable transaction's "is this actually
 * completed" and "one per transaction" gating lives with whichever
 * endpoint calls this (booking/project completion flow), not duplicated
 * here — this Action is the write path + notification trigger.
 */
final class SubmitReview implements Action
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function handle(User $reviewer, User $reviewee, Model $reviewable, int $rating, ?string $comment): Review
    {
        if ($reviewer->id === $reviewee->id) {
            throw new ConflictException('You cannot review yourself.', 'self_review_not_allowed');
        }

        $editWindowHours = (int) $this->settings->get('review.edit_window_hours', 72);

        $review = Review::create([
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $reviewee->id,
            'reviewable_type' => $reviewable->getMorphClass(),
            'reviewable_id' => $reviewable->getKey(),
            'rating' => $rating,
            'comment' => $comment,
            'edit_locked_at' => now()->addHours($editWindowHours),
        ]);

        ReviewReceived::dispatch($review);
        SyncBusinessRatingAverageJob::dispatch($review);

        return $review;
    }
}
