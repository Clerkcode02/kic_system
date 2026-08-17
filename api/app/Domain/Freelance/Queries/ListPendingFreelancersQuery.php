<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Queries;

use App\Domain\Freelance\Enums\FreelancerApprovalStatus;
use App\Domain\Freelance\Models\FreelancerProfile;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /admin/freelancers/verification-queue (SRS §12 "verification queue").
 * Defaults to pending applications; an explicit `status` filter lets the
 * admin browse approved/rejected history too.
 */
final class ListPendingFreelancersQuery
{
    private const PER_PAGE = 20;

    /**
     * @param  array{status?: string}  $filters
     * @return CursorPaginator<int, FreelancerProfile>
     */
    public function handle(array $filters = []): CursorPaginator
    {
        $status = $filters['status'] ?? FreelancerApprovalStatus::Pending->value;

        return FreelancerProfile::query()
            ->with(['user:id,name,email', 'portfolioItems', 'skills'])
            ->where('approval_status', $status)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
