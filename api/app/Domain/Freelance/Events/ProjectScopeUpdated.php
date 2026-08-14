<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Events;

use App\Domain\Freelance\Models\Project;
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
     */
    public function __construct(
        public readonly Project $project,
        public readonly array $affectedFreelancerUserIds,
    ) {
    }
}
