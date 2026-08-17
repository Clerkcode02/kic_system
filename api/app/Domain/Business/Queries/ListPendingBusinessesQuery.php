<?php

declare(strict_types=1);

namespace App\Domain\Business\Queries;

use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Business\Models\Business;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /admin/businesses/verification-queue (SRS §12 "verification queue").
 * Defaults to pending applications; an explicit `status` filter lets the
 * admin browse verified/rejected history too.
 */
final class ListPendingBusinessesQuery
{
    private const PER_PAGE = 20;

    /**
     * @param  array{status?: string}  $filters
     * @return CursorPaginator<int, Business>
     */
    public function handle(array $filters = []): CursorPaginator
    {
        $status = $filters['status'] ?? BusinessVerificationStatus::Pending->value;

        return Business::query()
            ->with(['user:id,name,email', 'documents'])
            ->where('verification_status', $status)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
