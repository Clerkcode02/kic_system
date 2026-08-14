<?php

declare(strict_types=1);

namespace App\Domain\Freelance\StateMachines;

use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Support\AbstractStateMachine;

/**
 * SRS §10: pending --> submitted (freelancer submits deliverables) -->
 * approved (client approves) --> paid (escrow released, Phase 7) |
 * disputed (client rejects) --> submitted (freelancer resubmits).
 */
final class MilestoneStateMachine extends AbstractStateMachine
{
    /**
     * @var array<string, array<int, string>>
     */
    protected array $transitions = [
        'pending' => ['submitted'],
        'submitted' => ['approved', 'disputed'],
        'approved' => ['paid'],
        'disputed' => ['submitted'],
        'paid' => [],
    ];

    public function __construct(MilestoneStatus|string $initialState)
    {
        parent::__construct($initialState instanceof MilestoneStatus ? $initialState->value : $initialState);
    }
}
