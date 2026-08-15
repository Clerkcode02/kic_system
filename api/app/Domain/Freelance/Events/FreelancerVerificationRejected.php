<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Events;

use App\Domain\Freelance\Models\FreelancerProfile;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FreelancerVerificationRejected implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FreelancerProfile $profile,
        public readonly ?string $reason,
    ) {
    }

    public function auditActorId(): ?string
    {
        return null;
    }

    public function auditAction(): string
    {
        return 'freelancer.verification_rejected';
    }

    public function auditableType(): string
    {
        return 'freelancer';
    }

    public function auditableId(): string
    {
        return $this->profile->id;
    }

    public function auditBeforeState(): ?array
    {
        return ['approval_status' => 'pending'];
    }

    public function auditAfterState(): ?array
    {
        return ['approval_status' => 'rejected', 'reason' => $this->reason];
    }
}
