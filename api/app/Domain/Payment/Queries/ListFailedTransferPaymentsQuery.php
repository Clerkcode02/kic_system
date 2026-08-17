<?php

declare(strict_types=1);

namespace App\Domain\Payment\Queries;

use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /admin/payouts/failed-transfers — surfaces milestone escrow releases
 * whose Stripe Transfer failed (see ReconcileTransfer), so an admin can
 * retry them (SRS §12 "payout batch monitor: failures, retry").
 */
final class ListFailedTransferPaymentsQuery
{
    private const PER_PAGE = 20;

    /**
     * @return CursorPaginator<int, Payment>
     */
    public function handle(): CursorPaginator
    {
        return Payment::query()
            ->where('status', PaymentStatus::Failed)
            ->whereNotNull('stripe_transfer_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
