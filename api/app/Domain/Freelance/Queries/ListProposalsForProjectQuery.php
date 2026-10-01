<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Queries;

use App\Domain\Freelance\Models\Proposal;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /projects/{id}/proposals — client-only (a freelancer's competing
 * proposals aren't visible to other freelancers), enforced by
 * ProjectPolicy::viewProposals via IndexProposalRequest.
 */
final class ListProposalsForProjectQuery
{
    private const PER_PAGE = 20;

    /**
     * Allowed `?sort=` values. Kept as an allow-list that maps to a fixed
     * column/direction pair rather than accepting a column name — a
     * client-supplied identifier must never reach the ORDER BY clause.
     *
     * @var list<string>
     */
    public const SORTS = ['newest', 'amount_asc', 'amount_desc', 'delivery_asc', 'rating_desc'];

    /**
     * @param  array{sort?: string, cursor?: string}  $filters
     * @return CursorPaginator<int, Proposal>
     */
    public function handle(string $projectId, array $filters = []): CursorPaginator
    {
        $query = Proposal::query()
            ->where('project_id', $projectId)
            ->with(['freelancer:id,user_id,headline,rating_avg', 'freelancer.user:id,name']);

        $this->applySort($query, $filters['sort'] ?? 'newest');

        // `id` is always the final tiebreaker: cursor pagination needs a
        // deterministic total order, and every sort column above can tie.
        return $query->orderBy('id')->cursorPaginate(self::PER_PAGE);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Proposal>  $query
     */
    private function applySort(\Illuminate\Database\Eloquent\Builder $query, string $sort): void
    {
        match ($sort) {
            'amount_asc' => $query->orderBy('proposed_amount'),
            'amount_desc' => $query->orderByDesc('proposed_amount'),
            'delivery_asc' => $query->orderBy('delivery_days'),
            // Rating lives on the freelancer profile, so this needs a join
            // rather than an orderBy on the proposals table. Selecting
            // proposals.* keeps the hydrated model free of joined columns.
            'rating_desc' => $query
                ->select('proposals.*')
                ->join('freelancer_profiles', 'freelancer_profiles.id', '=', 'proposals.freelancer_id')
                ->orderByDesc('freelancer_profiles.rating_avg'),
            default => $query->orderByDesc('created_at'),
        };
    }
}
