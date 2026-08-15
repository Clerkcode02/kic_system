<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Events;

use App\Domain\Freelance\Models\Project;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SRS §19: "scope edits after proposals exist trigger notifications to all
 * applicants." Fired by UpdateProject only when the project already has at
 * least one proposal at the time of the edit; a Notification-module
 * listener (not yet built) is what would actually deliver these.
 */
class ProjectScopeUpdated implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<string>  $affectedFreelancerUserIds
     * @param  array<string, mixed>|null  $beforeState
     * @param  array<string, mixed>|null  $afterState
     */
    public function __construct(
        public readonly Project $project,
        public readonly array $affectedFreelancerUserIds,
        public readonly ?User $actor = null,
        public readonly ?array $beforeState = null,
        public readonly ?array $afterState = null,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->actor?->id;
    }

    public function auditAction(): string
    {
        return 'project.updated';
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
        return $this->beforeState;
    }

    public function auditAfterState(): ?array
    {
        return $this->afterState;
    }
}
