<?php

declare(strict_types=1);

namespace App\Domain\Dispute\Events;

use App\Domain\Dispute\Models\Dispute;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisputeResolved implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Dispute $dispute,
        public readonly User $actor,
        public readonly string $previousStatus,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->actor->id;
    }

    public function auditAction(): string
    {
        return 'dispute.resolved';
    }

    public function auditableType(): string
    {
        return 'dispute';
    }

    public function auditableId(): string
    {
        return $this->dispute->id;
    }

    public function auditBeforeState(): ?array
    {
        return ['status' => $this->previousStatus];
    }

    public function auditAfterState(): ?array
    {
        return ['status' => $this->dispute->status->value, 'resolution_notes' => $this->dispute->resolution_notes];
    }
}
