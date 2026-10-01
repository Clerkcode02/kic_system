<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Queries;

use App\Domain\Freelance\Models\Contract;
use App\Domain\User\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /me/contracts (and the older /freelancers/me/contracts) — every
 * contract the caller is a party to, from either side.
 *
 * A contract has exactly two parties: the project's client and the hired
 * proposal's freelancer. Matching both with an OR rather than branching on
 * the caller's role keeps one query serving both dashboards, and stays
 * correct for a user who holds both roles (they'd see each contract once,
 * whichever side they're on). A caller who is party to nothing — including a
 * freelancer with no profile yet — gets an empty page.
 */
final class ListMyContractsQuery
{
    private const PER_PAGE = 20;

    /**
     * @param  array{cursor?: string}  $filters
     * @return CursorPaginator<int, Contract>
     */
    public function handle(User $user, array $filters = []): CursorPaginator
    {
        $freelancerProfileId = $user->freelancerProfile?->id;

        $query = Contract::query()
            ->with(['project:id,title,status,client_id', 'milestones'])
            ->where(function ($partyQuery) use ($user, $freelancerProfileId) {
                $partyQuery->whereHas('project', function ($projectQuery) use ($user) {
                    $projectQuery->where('client_id', $user->id);
                });

                if ($freelancerProfileId !== null) {
                    $partyQuery->orWhereHas('proposal', function ($proposalQuery) use ($freelancerProfileId) {
                        $proposalQuery->where('freelancer_id', $freelancerProfileId);
                    });
                }
            });

        return $query
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
