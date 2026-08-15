<?php

declare(strict_types=1);

namespace App\Domain\Payment\Queries;

use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /freelancer/me/earnings — freelancers have no Payout ledger (only
 * bookings get swept into `payouts` by RunProviderPayoutJob). Their income
 * is the succeeded `Payment` rows against their own milestones —
 * ReleaseMilestoneEscrow stamps `stripe_transfer_id`/`provider_net_amount`
 * onto that same row once escrow is released, so this doubles as "which
 * milestones have actually paid out" (a null `stripe_transfer_id` means the
 * milestone is funded/escrowed but not yet released).
 */
final class ListFreelancerEarningsQuery
{
    private const PER_PAGE = 20;

    /**
     * @return CursorPaginator<int, Payment>
     */
    public function handle(FreelancerProfile $freelancer): CursorPaginator
    {
        $milestoneIds = Milestone::query()
            ->whereHas('contract.proposal', function ($proposalQuery) use ($freelancer) {
                $proposalQuery->where('freelancer_id', $freelancer->id);
            })
            ->pluck('id');

        return Payment::query()
            ->where('payable_type', 'milestone')
            ->whereIn('payable_id', $milestoneIds)
            ->where('status', PaymentStatus::Succeeded)
            ->with('payable')
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
