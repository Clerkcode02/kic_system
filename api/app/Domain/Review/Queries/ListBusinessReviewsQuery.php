<?php

declare(strict_types=1);

namespace App\Domain\Review\Queries;

use App\Domain\Business\Models\Business;
use App\Domain\Review\Models\Review;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /businesses/{id}/reviews — public, like the service/category
 * browsing endpoints (CLAUDE.md §4: browsing is ✅ for every role,
 * including anonymous visitors). Reviews target the business owner's user
 * id (see SubmitBookingReview), not the business row directly.
 */
final class ListBusinessReviewsQuery
{
    private const PER_PAGE = 20;

    /**
     * @return CursorPaginator<int, Review>
     */
    public function handle(Business $business): CursorPaginator
    {
        return Review::query()
            ->where('reviewee_id', $business->user_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
