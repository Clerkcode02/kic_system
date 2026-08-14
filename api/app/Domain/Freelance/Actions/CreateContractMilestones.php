<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Freelance\Enums\ContractStatus;
use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Freelance\Events\ContractMilestonesDefined;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS §19/§10: milestone amounts must sum to the contract total — validated
 * here (server-side source of truth) and mirrored in
 * StoreContractMilestonesRequest for a friendly 422. Bulk-define is a full
 * replace: calling it again is only allowed while every existing milestone
 * is still `pending` (nothing submitted/approved/paid yet), which is what
 * "validate on ... any edit" means in practice — once work has started the
 * milestone breakdown is locked.
 */
final class CreateContractMilestones implements Action
{
    /**
     * @param  list<array{title: string, amount: string|float, due_date: string}>  $milestones
     * @return Collection<int, Milestone>
     */
    public function handle(Contract $contract, array $milestones, User $actor): Collection
    {
        if ($contract->status !== ContractStatus::Active) {
            throw new ConflictException('Milestones can only be defined on an active contract.', 'contract_not_active');
        }

        $existing = $contract->milestones()->get();

        if ($existing->isNotEmpty() && $existing->contains(fn (Milestone $m) => $m->status !== MilestoneStatus::Pending)) {
            throw new ConflictException(
                'Milestones cannot be redefined once work has begun on this contract.',
                'milestones_locked',
            );
        }

        $sum = Money::fromMinorUnits(0, $contract->currency);

        foreach ($milestones as $milestone) {
            $sum = $sum->add(Money::fromDecimal((string) $milestone['amount'], $contract->currency));
        }

        if (! $sum->equals($contract->total_amount)) {
            throw ValidationException::withMessages([
                'milestones' => "Milestone amounts must sum to the contract total ({$contract->total_amount->toDecimal()} {$contract->currency}).",
            ]);
        }

        return DB::transaction(function () use ($contract, $milestones, $actor, $existing) {
            if ($existing->isNotEmpty()) {
                Milestone::query()->where('contract_id', $contract->id)->delete();
            }

            $created = collect($milestones)->map(fn (array $milestone) => Milestone::create([
                'contract_id' => $contract->id,
                'title' => $milestone['title'],
                'amount' => Money::fromDecimal((string) $milestone['amount'], $contract->currency),
                'currency' => $contract->currency,
                'due_date' => $milestone['due_date'],
                'status' => MilestoneStatus::Pending,
            ]));

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'contract.milestones_defined',
                'auditable_type' => 'contract',
                'auditable_id' => $contract->id,
                'before_state' => ['milestone_count' => $existing->count()],
                'after_state' => ['milestone_count' => $created->count()],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            ContractMilestonesDefined::dispatch($contract->fresh(), $actor);

            return $created;
        });
    }
}
