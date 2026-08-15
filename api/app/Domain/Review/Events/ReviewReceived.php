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

    public function auditActorId(): ?string
    {
        return $this->review->reviewer_id;
    }

    public function auditAction(): string
    {
        return 'review.submitted';
    }

    public function auditableType(): string
    {
        return 'review';
    }

    public function auditableId(): string
    {
        return $this->review->id;
    }

    public function auditBeforeState(): ?array
    {
        return null;
    }

    public function auditAfterState(): ?array
    {
        return ['rating' => $this->review->rating, 'reviewee_id' => $this->review->reviewee_id];
    }
}
