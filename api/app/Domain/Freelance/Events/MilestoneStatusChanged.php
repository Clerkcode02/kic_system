<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Events;

use App\Domain\Freelance\Models\Milestone;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MilestoneStatusChanged implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Milestone $milestone,
        public readonly string $fromStatus,
        public readonly string $toStatus,
        public readonly ?User $actor,
        public readonly ?string $note = null,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->actor?->id;
    }

    public function auditAction(): string
    {
        return 'milestone.status_changed';
    }

    public function auditableType(): string
    {
        return 'milestone';
    }

    public function auditableId(): string
    {
        return $this->milestone->id;
    }

    public function auditBeforeState(): ?array
    {
        return ['status' => $this->fromStatus];
    }

    public function auditAfterState(): ?array
    {
        return ['status' => $this->toStatus, 'note' => $this->note];
    }
}
