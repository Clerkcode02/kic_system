<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Events;

use App\Domain\Freelance\Models\Proposal;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProposalSubmitted implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Proposal $proposal)
    {
    }

    public function auditActorId(): ?string
    {
        return $this->proposal->freelancer->user_id;
    }

    public function auditAction(): string
    {
        return 'proposal.submitted';
    }

    public function auditableType(): string
    {
        return 'proposal';
    }

    public function auditableId(): string
    {
        return $this->proposal->id;
    }

    public function auditBeforeState(): ?array
    {
        return null;
    }

    public function auditAfterState(): ?array
    {
        return ['status' => $this->proposal->status->value];
    }
}
