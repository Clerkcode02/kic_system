<?php

declare(strict_types=1);

namespace App\Domain\Review\Jobs;

use App\Domain\Business\Models\Business;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Review\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * CLAUDE.md §8 queue table: "SyncBusinessRatingAverageJob | New review
 * submitted". A reviewee is either a business owner (booking review) or a
 * freelancer (project review) — never both — so this recalculates
 * whichever `rating_avg` the reviewee actually owns.
 */
class SyncBusinessRatingAverageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Review $review)
    {
    }

    public function handle(): void
    {
        $revieweeId = $this->review->reviewee_id;

        $average = round((float) Review::query()->where('reviewee_id', $revieweeId)->avg('rating'), 2);

        $business = Business::query()->where('user_id', $revieweeId)->first();

        if ($business !== null) {
            $business->update(['rating_avg' => $average]);

            return;
        }

        FreelancerProfile::query()->where('user_id', $revieweeId)->first()?->update(['rating_avg' => $average]);
    }
}
