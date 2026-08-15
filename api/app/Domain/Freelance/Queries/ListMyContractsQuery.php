<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Queries;

use App\Domain\Freelance\Models\Contract;
use App\Domain\User\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /freelancer/me/contracts — contracts created from one of the caller's
 * own hired proposals. A freelancer with no profile (or no hired proposals
 * yet) simply gets an empty page, mirroring ListMyProposalsQuery.
 */
final class ListMyContractsQuery
{
    private const PER_PAGE = 20;

    /**
     * @param  array{cursor?: string}  $filters
     * @return CursorPaginator<int, Contract>
     */
    public function handle(User $freelancerUser, array $filters = []): CursorPaginator
    {
        $query = Contract::query()->with(['project:id,title,status', 'milestones']);

        $freelancerProfileId = $freelancerUser->freelancerProfile?->id;

        if ($freelancerProfileId === null) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereHas('proposal', function ($proposalQuery) use ($freelancerProfileId) {
                $proposalQuery->where('freelancer_id', $freelancerProfileId);
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
