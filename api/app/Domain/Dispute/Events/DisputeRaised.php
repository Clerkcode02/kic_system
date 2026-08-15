<?php

declare(strict_types=1);

namespace App\Domain\Dispute\Events;

use App\Domain\Dispute\Models\Dispute;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisputeRaised implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Dispute $dispute,
        public readonly User $actor,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->actor->id;
    }

    public function auditAction(): string
    {
        return 'dispute.raised';
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
        return null;
    }

    public function auditAfterState(): ?array
    {
        return [
            'status' => $this->dispute->status->value,
            'disputable_type' => $this->dispute->disputable_type,
            'disputable_id' => $this->dispute->disputable_id,
        ];
    }
}
