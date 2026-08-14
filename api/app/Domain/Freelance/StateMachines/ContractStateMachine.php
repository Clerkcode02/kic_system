<?php

declare(strict_types=1);

namespace App\Domain\Freelance\StateMachines;

use App\Domain\Freelance\Enums\ContractStatus;
use App\Support\AbstractStateMachine;

/**
 * SRS §10: a contract auto-completes when every milestone reaches `paid`
 * (see CompleteContract). Terminated is reachable only via dispute
 * resolution, which is out of this module's scope for now.
 */
final class ContractStateMachine extends AbstractStateMachine
{
    /**
     * @var array<string, array<int, string>>
     */
    protected array $transitions = [
        'active' => ['completed'],
        'completed' => [],
        'terminated' => [],
    ];

    public function __construct(ContractStatus|string $initialState)
    {
        parent::__construct($initialState instanceof ContractStatus ? $initialState->value : $initialState);
    }
}
