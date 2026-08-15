<?php

declare(strict_types=1);

namespace App\Domain\Review\Actions;

use App\Domain\Review\Models\Review;
use App\Support\Action;
use App\Support\ConflictException;

/**
 * SRS §19: "provider may reply once" — reply is a single write, never
 * revised. Who may call this (only the reviewee) is gated by ReviewPolicy.
 */
final class ReplyToReview implements Action
{
    public function handle(Review $review, string $reply): Review
    {
        if ($review->provider_reply !== null) {
            throw new ConflictException('This review has already been replied to.', 'reply_already_exists');
        }

        $review->update(['provider_reply' => $reply]);

        return $review->fresh();
    }
}
