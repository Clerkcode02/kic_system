<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Queries;

use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Freelance\Enums\ProposalStatus;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Collection;

/**
 * GET /freelancer/me/dashboard — the freelancer home screen glance view:
 * open proposal count, active contract count, milestones needing attention
 * (submitted awaiting client approval, or disputed and needing resubmit),
 * and a lifetime earnings total (released escrow only, matching
 * ListFreelancerEarningsQuery's definition of "earned").
 */
final class FreelancerDashboardSummaryQuery
{
    private const LIST_LIMIT = 10;

    /**
     * @return array{
     *     open_proposal_count: int,
     *     active_contract_count: int,
     *     attention_milestones: Collection<int, Milestone>,
     *     earnings_total: Money,
     * }
     */
    public function handle(FreelancerProfile $freelancer): array
    {
        $openProposalCount = Proposal::query()
            ->where('freelancer_id', $freelancer->id)
            ->whereIn('status', [ProposalStatus::Submitted, ProposalStatus::Shortlisted])
            ->count();

        $contractIds = Contract::query()
            ->whereHas('proposal', fn ($q) => $q->where('freelancer_id', $freelancer->id))
            ->pluck('id');

        $activeContractCount = Contract::query()
            ->whereIn('id', $contractIds)
            ->where('status', 'active')
            ->count();

        $attentionMilestones = Milestone::query()
            ->whereIn('contract_id', $contractIds)
            ->whereIn('status', [MilestoneStatus::Submitted, MilestoneStatus::Disputed])
            ->with('contract:id,project_id')
            ->orderByDesc('updated_at')
            ->limit(self::LIST_LIMIT)
            ->get();

        $milestoneIds = Milestone::query()->whereIn('contract_id', $contractIds)->pluck('id');

        $earningsTotal = Payment::query()
            ->where('payable_type', 'milestone')
            ->whereIn('payable_id', $milestoneIds)
            ->where('status', PaymentStatus::Succeeded)
            ->whereNotNull('stripe_transfer_id')
            ->get()
            ->reduce(
                fn (?Money $carry, Payment $p) => $carry === null ? $p->provider_net_amount : $carry->add($p->provider_net_amount),
                null,
            ) ?? Money::fromMinorUnits(0, 'CAD');

        return [
            'open_proposal_count' => $openProposalCount,
            'active_contract_count' => $activeContractCount,
            'attention_milestones' => $attentionMilestones,
            'earnings_total' => $earningsTotal,
        ];
    }
}
