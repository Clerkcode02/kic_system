<?php

declare(strict_types=1);

namespace App\Domain\Payment\Queries;

use App\Domain\Business\Models\Business;
use App\Domain\Payment\Models\Payout;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /provider/me/earnings — the provider's own Payout ledger. CAD only,
 * no currency grouping/conversion (CLAUDE.md §5 — Canada-only launch).
 */
final class ListProviderEarningsQuery
{
    private const PER_PAGE = 20;

    /**
     * @return CursorPaginator<int, Payout>
     */
    public function handle(Business $business): CursorPaginator
    {
        return Payout::query()
            ->where('provider_id', $business->id)
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
