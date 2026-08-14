<?php

declare(strict_types=1);

namespace App\Domain\Freelance\StateMachines;

use App\Domain\Freelance\Enums\ProjectStatus;
use App\Support\AbstractStateMachine;

/**
 * SRS §10: Open --> InProgress (client hires, Contract created) --> Completed
 * (all milestones approved & paid). Cancelled is reachable from either Open
 * (before hire) or InProgress (mutual/dispute) but never from Completed.
 */
final class ProjectStateMachine extends AbstractStateMachine
{
    /**
     * @var array<string, array<int, string>>
     */
    protected array $transitions = [
        'open' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(ProjectStatus|string $initialState)
    {
        parent::__construct($initialState instanceof ProjectStatus ? $initialState->value : $initialState);
    }
}
