<?php

declare(strict_types=1);

namespace App\Domain\Freelance\StateMachines;

use App\Domain\Freelance\Enums\ProposalStatus;
use App\Support\AbstractStateMachine;

/**
 * A proposal can be shortlisted before hire, but hiring (HireFreelancer)
 * accepts one proposal and rejects every other Submitted/Shortlisted
 * sibling directly — there is no separate "reject" endpoint a client calls
 * per-proposal, so `rejected` only appears as a side effect of hiring.
 */
final class ProposalStateMachine extends AbstractStateMachine
{
    /**
     * @var array<string, array<int, string>>
     */
    protected array $transitions = [
        'submitted' => ['shortlisted', 'accepted', 'rejected', 'withdrawn'],
        'shortlisted' => ['accepted', 'rejected', 'withdrawn'],
        'accepted' => [],
        'rejected' => [],
        'withdrawn' => [],
    ];

    public function __construct(ProposalStatus|string $initialState)
    {
        parent::__construct($initialState instanceof ProposalStatus ? $initialState->value : $initialState);
    }
}
