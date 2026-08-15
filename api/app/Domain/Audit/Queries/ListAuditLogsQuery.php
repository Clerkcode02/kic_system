<?php

declare(strict_types=1);

namespace App\Domain\Audit\Queries;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * GET /audit-logs?actor=&action=&entity=&date_from=&date_to=&cursor= —
 * mirrors AuditLogPolicy exactly: a caller with `audit-logs.view` (admins)
 * sees every entry; a caller with only `audit-logs.view-own` (providers,
 * freelancers) sees entries where they are the actor OR the auditable
 * subject belongs to them (SRS §13 "transparency on disputes"). A customer
 * holds neither permission and never reaches this query (AuditLogPolicy::viewAny
 * denies at the controller).
 */
final class ListAuditLogsQuery
{
    private const PER_PAGE = 20;

    /**
     * @param  array{actor?: string, action?: string, entity?: string, date_from?: string, date_to?: string, cursor?: string}  $filters
     * @return CursorPaginator<int, AuditLog>
     */
    public function handle(User $user, array $filters): CursorPaginator
    {
        $query = AuditLog::query()->with('actor:id,name');

        if (! $user->can(PermissionName::AuditLogsView->value)) {
            $query->where(fn (Builder $q) => $this->ownScope($q, $user));
        }

        if (! empty($filters['actor'])) {
            $query->where('actor_id', $filters['actor']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['entity'])) {
            $query->where('auditable_type', $filters['entity']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    private function ownScope(Builder $query, User $user): Builder
    {
        return $query
            ->where('actor_id', $user->id)
            ->orWhere(fn (Builder $q) => $q->where('auditable_type', 'user')->where('auditable_id', $user->id))
            ->orWhere(fn (Builder $q) => $q->where('auditable_type', 'booking')->whereIn('auditable_id', function ($sub) use ($user) {
                $sub->select('id')->from('bookings')
                    ->where('customer_id', $user->id)
                    ->orWhereIn('provider_id', function ($providerSub) use ($user) {
                        $providerSub->select('id')->from('businesses')->where('user_id', $user->id);
                    });
            }))
            ->orWhere(fn (Builder $q) => $q->where('auditable_type', 'project')->whereIn('auditable_id', function ($sub) use ($user) {
                $sub->select('id')->from('projects')->where('client_id', $user->id);
            }))
            ->orWhere(fn (Builder $q) => $q->where('auditable_type', 'milestone')->whereIn('auditable_id', function ($sub) use ($user) {
                $sub->select('milestones.id')->from('milestones')
                    ->join('contracts', 'contracts.id', '=', 'milestones.contract_id')
                    ->join('projects', 'projects.id', '=', 'contracts.project_id')
                    ->join('proposals', 'proposals.id', '=', 'contracts.proposal_id')
                    ->join('freelancer_profiles', 'freelancer_profiles.id', '=', 'proposals.freelancer_id')
                    ->where('projects.client_id', $user->id)
                    ->orWhere('freelancer_profiles.user_id', $user->id);
            }))
            ->orWhere(fn (Builder $q) => $q->where('auditable_type', 'deliverable')->whereIn('auditable_id', function ($sub) use ($user) {
                $sub->select('deliverables.id')->from('deliverables')
                    ->join('milestones', 'milestones.id', '=', 'deliverables.milestone_id')
                    ->join('contracts', 'contracts.id', '=', 'milestones.contract_id')
                    ->join('projects', 'projects.id', '=', 'contracts.project_id')
                    ->join('proposals', 'proposals.id', '=', 'contracts.proposal_id')
                    ->join('freelancer_profiles', 'freelancer_profiles.id', '=', 'proposals.freelancer_id')
                    ->where('projects.client_id', $user->id)
                    ->orWhere('freelancer_profiles.user_id', $user->id);
            }))
            ->orWhere(fn (Builder $q) => $q->where('auditable_type', 'proposal')->whereIn('auditable_id', function ($sub) use ($user) {
                $sub->select('proposals.id')->from('proposals')
                    ->join('projects', 'projects.id', '=', 'proposals.project_id')
                    ->join('freelancer_profiles', 'freelancer_profiles.id', '=', 'proposals.freelancer_id')
                    ->where('projects.client_id', $user->id)
                    ->orWhere('freelancer_profiles.user_id', $user->id);
            }))
            ->orWhere(fn (Builder $q) => $q->where('auditable_type', 'dispute')->whereIn('auditable_id', function ($sub) use ($user) {
                $sub->select('id')->from('disputes')->where('raised_by', $user->id);
            }));
    }
}
