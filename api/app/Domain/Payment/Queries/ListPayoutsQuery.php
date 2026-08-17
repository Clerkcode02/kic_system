<?php

declare(strict_types=1);

namespace App\Domain\Payment\Queries;

use App\Domain\Payment\Models\Payout;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /admin/payouts — admin-only ledger view (SRS §12 "payout batch
 * monitor"). No batch entity exists; the UI groups these rows by
 * `created_at` day itself.
 */
final class ListPayoutsQuery
{
    private const PER_PAGE = 20;

    /**
     * @param  array{status?: string, provider_id?: string, date_from?: string, date_to?: string}  $filters
     * @return CursorPaginator<int, Payout>
     */
    public function handle(array $filters): CursorPaginator
    {
        $query = Payout::query()->with('provider:id,legal_name');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['provider_id'])) {
            $query->where('provider_id', $filters['provider_id']);
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
}
