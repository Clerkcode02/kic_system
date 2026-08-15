<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Events;

use App\Domain\Freelance\Models\Project;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectPublished implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Project $project)
    {
    }

    public function auditActorId(): ?string
    {
        return $this->project->client_id;
    }

    public function auditAction(): string
    {
        return 'project.published';
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
        return null;
    }

    public function auditAfterState(): ?array
    {
        return ['status' => $this->project->status->value];
    }
}
