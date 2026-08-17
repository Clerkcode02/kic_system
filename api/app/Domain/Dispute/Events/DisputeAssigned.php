<?php

declare(strict_types=1);

namespace App\Domain\Dispute\Events;

use App\Domain\Dispute\Models\Dispute;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisputeAssigned implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Dispute $dispute,
        public readonly User $admin,
        public readonly User $actor,
        public readonly ?string $previousAssignedAdminId,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->actor->id;
    }

    public function auditAction(): string
    {
        return 'dispute.assigned';
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
        return ['assigned_admin_id' => $this->previousAssignedAdminId];
    }

    public function auditAfterState(): ?array
    {
        return ['assigned_admin_id' => $this->admin->id];
    }
}
