<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Booking\Models\Booking;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Models\Deliverable;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Project;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;
use App\Policies\Concerns\GrantsAdminOversight;

/**
 * Admins see every entry. Providers/freelancers see only entries where
 * they are the actor, or the auditable subject belongs to them — never
 * customers, who hold neither audit-log permission (SRS §6: "View audit
 * trail" is ❌ for Customer).
 */
class AuditLogPolicy
{
    use GrantsAdminOversight;

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AuditLogsView->value) || $user->can(PermissionName::AuditLogsViewOwn->value);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if ($this->isPlatformAdmin($user) && $user->can(PermissionName::AuditLogsView->value)) {
            return true;
        }

        if (! $user->can(PermissionName::AuditLogsViewOwn->value)) {
            return false;
        }

        if ($auditLog->actor_id === $user->id) {
            return true;
        }

        return $this->subjectBelongsToUser($user, $auditLog);
    }

    private function subjectBelongsToUser(User $user, AuditLog $auditLog): bool
    {
        $subject = $auditLog->auditable;

        if ($subject === null) {
            return false;
        }

        return match (true) {
            $subject instanceof User => $subject->id === $user->id,
            $subject instanceof Booking => $subject->customer_id === $user->id || $subject->provider->user_id === $user->id,
            $subject instanceof Project => $subject->client_id === $user->id,
            $subject instanceof Milestone => $subject->contract->project->client_id === $user->id
                || $subject->contract->proposal->freelancer->user_id === $user->id,
            $subject instanceof Deliverable => $subject->milestone->contract->project->client_id === $user->id
                || $subject->milestone->contract->proposal->freelancer->user_id === $user->id,
            $subject instanceof Dispute => $subject->raised_by === $user->id,
            default => false,
        };
    }
}
