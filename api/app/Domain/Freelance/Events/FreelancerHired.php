<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Events;

use App\Domain\Freelance\Models\Contract;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FreelancerHired implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Contract $contract,
        public readonly ?User $actor = null,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->actor?->id;
    }

    public function auditAction(): string
    {
        return 'proposal.hired';
    }

    public function auditableType(): string
    {
        return 'proposal';
    }

    public function auditableId(): string
    {
        return $this->contract->proposal_id;
    }

    public function auditBeforeState(): ?array
    {
        return ['status' => 'submitted_or_shortlisted'];
    }

    public function auditAfterState(): ?array
    {
        return ['status' => 'accepted', 'contract_id' => $this->contract->id];
    }
}
