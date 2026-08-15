<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Events;

use App\Domain\Freelance\Models\Project;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectStatusChanged implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Project $project,
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
        return 'project.status_changed';
    }

    public function auditableType(): string
    {
        return 'project';
    }

    public function auditableId(): string
    {
        return $this->project->id;
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
