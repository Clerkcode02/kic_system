<?php

declare(strict_types=1);

namespace App\Domain\Business\Events;

use App\Domain\Business\Models\Business;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BusinessVerificationRejected implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Business $business,
        public readonly ?string $reason,
    ) {
    }

    public function auditActorId(): ?string
    {
        return null;
    }

    public function auditAction(): string
    {
        return 'business.verification_rejected';
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
        return ['verification_status' => 'pending'];
    }

    public function auditAfterState(): ?array
    {
        return ['verification_status' => 'rejected', 'reason' => $this->reason];
    }
}
