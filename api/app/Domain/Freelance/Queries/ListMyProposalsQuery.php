<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Queries;

use App\Domain\Freelance\Models\Proposal;
use App\Domain\User\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /freelancers/me/proposals — scoped to the caller's own
 * FreelancerProfile; a freelancer with no profile simply gets an empty page
 * rather than an error (mirrors ListBookingsQuery's provider-with-no-business
 * handling).
 */
final class ListMyProposalsQuery
{
    private const PER_PAGE = 20;

    /**
     * @param  array{cursor?: string}  $filters
     * @return CursorPaginator<int, Proposal>
     */
    public function handle(User $freelancerUser, array $filters = []): CursorPaginator
    {
        $query = Proposal::query()->with(['project:id,title,status,category_id', 'project.category:id,name']);

        $freelancerProfileId = $freelancerUser->freelancerProfile?->id;

        if ($freelancerProfileId === null) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where('freelancer_id', $freelancerProfileId);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
