<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Booking\Models\Booking;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Models\Deliverable;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Project;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;
use App\Policies\Concerns\GrantsAdminOversight;

class DisputePolicy
{
    use GrantsAdminOversight;

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::DisputesView->value);
    }

    public function view(User $user, Dispute $dispute): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $user->can(PermissionName::DisputesView->value);
        }

        return $user->can(PermissionName::DisputesView->value)
            && ($dispute->raised_by === $user->id || $this->isPartyToDisputable($user, $dispute));
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::DisputesCreate->value);
    }

    public function resolve(User $user, Dispute $dispute): bool
    {
        return $user->can(PermissionName::DisputesResolve->value);
    }

    /**
     * Gates the evidence-attachment lifecycle (presign/confirm/download-url)
     * for this dispute — same audience as {@see view}: the party who raised
     * it, the other party to the underlying booking/project/milestone, or
     * an admin with view rights.
     */
    public function manageEvidence(User $user, Dispute $dispute): bool
    {
        return $this->view($user, $dispute);
    }

    private function isPartyToDisputable(User $user, Dispute $dispute): bool
    {
        $disputable = $dispute->disputable;

        return match (true) {
            $disputable instanceof Booking => $disputable->customer_id === $user->id || $disputable->provider->user_id === $user->id,
            $disputable instanceof Project => $disputable->client_id === $user->id,
            $disputable instanceof Milestone => $disputable->contract->project->client_id === $user->id
                || $disputable->contract->proposal->freelancer->user_id === $user->id,
            $disputable instanceof Deliverable => $disputable->milestone->contract->project->client_id === $user->id
                || $disputable->milestone->contract->proposal->freelancer->user_id === $user->id,
            default => false,
        };
    }
}
