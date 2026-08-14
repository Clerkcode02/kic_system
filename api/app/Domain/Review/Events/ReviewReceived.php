<?php

declare(strict_types=1);

namespace App\Domain\Review\Events;

use App\Domain\Review\Models\Review;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewReceived implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Review $review)
    {
    }
}
