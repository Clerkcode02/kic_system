<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Events;

use App\Domain\Freelance\Models\Contract;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContractCompleted implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Contract $contract,
        public readonly ?User $actor = null,
        public readonly ?string $previousStatus = null,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->actor?->id;
    }

    public function auditAction(): string
    {
        return 'contract.completed';
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
        return $this->previousStatus !== null ? ['status' => $this->previousStatus] : null;
    }

    public function auditAfterState(): ?array
    {
        return ['status' => $this->contract->status->value];
    }
}
