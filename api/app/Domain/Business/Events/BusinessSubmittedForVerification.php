<?php

declare(strict_types=1);

namespace App\Domain\Business\Events;

use App\Domain\Business\Models\Business;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BusinessSubmittedForVerification implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Business $business,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->business->user_id;
    }

    public function auditAction(): string
    {
        return 'business.submitted_for_verification';
    }

    public function auditableType(): string
    {
        return 'business';
    }

    public function auditableId(): string
    {
        return $this->business->id;
    }

    public function auditBeforeState(): ?array
    {
        return null;
    }

    public function auditAfterState(): ?array
    {
        return ['verification_status' => 'pending'];
    }
}
