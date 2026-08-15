<?php

declare(strict_types=1);

namespace App\Domain\Dispute\Queries;

use App\Domain\Dispute\Models\Dispute;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;
use App\Policies\Concerns\GrantsAdminOversight;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * GET /disputes — mirrors DisputePolicy: admins with `disputes.view` see
 * every dispute; everyone else sees only disputes they raised or where
 * they're a party to the underlying booking/project/milestone/deliverable.
 */
final class ListDisputesQuery
{
    use GrantsAdminOversight;

    private const PER_PAGE = 20;

    /**
     * @return CursorPaginator<int, Dispute>
     */
    public function handle(User $user): CursorPaginator
    {
        $query = Dispute::query();

        if (! ($this->isPlatformAdmin($user) && $user->can(PermissionName::DisputesView->value))) {
            $query->where(fn (Builder $q) => $q
                ->where('raised_by', $user->id)
                ->orWhere(fn (Builder $q2) => $q2->where('disputable_type', 'booking')->whereIn('disputable_id', function ($sub) use ($user) {
                    $sub->select('id')->from('bookings')
                        ->where('customer_id', $user->id)
                        ->orWhereIn('provider_id', function ($providerSub) use ($user) {
                            $providerSub->select('id')->from('businesses')->where('user_id', $user->id);
                        });
                }))
                ->orWhere(fn (Builder $q2) => $q2->where('disputable_type', 'project')->whereIn('disputable_id', function ($sub) use ($user) {
                    $sub->select('id')->from('projects')->where('client_id', $user->id);
                }))
                ->orWhere(fn (Builder $q2) => $q2->where('disputable_type', 'milestone')->whereIn('disputable_id', function ($sub) use ($user) {
                    $sub->select('milestones.id')->from('milestones')
                        ->join('contracts', 'contracts.id', '=', 'milestones.contract_id')
                        ->join('projects', 'projects.id', '=', 'contracts.project_id')
                        ->join('proposals', 'proposals.id', '=', 'contracts.proposal_id')
                        ->join('freelancer_profiles', 'freelancer_profiles.id', '=', 'proposals.freelancer_id')
                        ->where('projects.client_id', $user->id)
                        ->orWhere('freelancer_profiles.user_id', $user->id);
                }))
                ->orWhere(fn (Builder $q2) => $q2->where('disputable_type', 'deliverable')->whereIn('disputable_id', function ($sub) use ($user) {
                    $sub->select('deliverables.id')->from('deliverables')
                        ->join('milestones', 'milestones.id', '=', 'deliverables.milestone_id')
                        ->join('contracts', 'contracts.id', '=', 'milestones.contract_id')
                        ->join('projects', 'projects.id', '=', 'contracts.project_id')
                        ->join('proposals', 'proposals.id', '=', 'contracts.proposal_id')
                        ->join('freelancer_profiles', 'freelancer_profiles.id', '=', 'proposals.freelancer_id')
                        ->where('projects.client_id', $user->id)
                        ->orWhere('freelancer_profiles.user_id', $user->id);
                })));
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
