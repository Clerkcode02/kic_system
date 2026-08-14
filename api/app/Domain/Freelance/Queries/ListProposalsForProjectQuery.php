<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Queries;

use App\Domain\Freelance\Models\Proposal;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /projects/{id}/proposals — client-only (a freelancer's competing
 * proposals aren't visible to other freelancers), enforced by the caller
 * checking ProposalPolicy-style ownership before invoking this query.
 */
final class ListProposalsForProjectQuery
{
    private const PER_PAGE = 20;

    /**
     * @param  array{cursor?: string}  $filters
     * @return CursorPaginator<int, Proposal>
     */
    public function handle(string $projectId, array $filters = []): CursorPaginator
    {
        return Proposal::query()
            ->where('project_id', $projectId)
            ->with(['freelancer:id,user_id,headline,rating_avg', 'freelancer.user:id,name'])
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
