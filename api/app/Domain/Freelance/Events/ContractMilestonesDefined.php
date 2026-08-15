<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Events;

use App\Domain\Freelance\Models\Contract;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContractMilestonesDefined implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Contract $contract,
        public readonly User $actor,
        public readonly int $previousMilestoneCount = 0,
        public readonly int $newMilestoneCount = 0,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->actor->id;
    }

    public function auditAction(): string
    {
        return 'contract.milestones_defined';
    }

    public function auditableType(): string
    {
        return 'contract';
    }

    public function auditableId(): string
    {
        return $this->contract->id;
    }

    public function auditBeforeState(): ?array
    {
        return ['milestone_count' => $this->previousMilestoneCount];
    }

    public function auditAfterState(): ?array
    {
        return ['milestone_count' => $this->newMilestoneCount];
    }
}
