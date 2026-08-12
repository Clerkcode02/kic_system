<?php

declare(strict_types=1);

namespace App\Support;

abstract class AbstractStateMachine
{
    /**
     * Map of from-state => list of allowed to-states.
     *
     * @var array<string, array<int, string>>
     */
    protected array $transitions = [];

    protected string $state;

    public function __construct(string $initialState)
    {
        $this->state = $initialState;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function canTransition(string $to): bool
    {
        return in_array($to, $this->transitions[$this->state] ?? [], true);
    }

    /**
     * @throws IllegalStateTransitionException
     */
    public function transition(string $to): string
    {
        if (! $this->canTransition($to)) {
            throw new IllegalStateTransitionException($this->state, $to, static::class);
        }

        return $this->state = $to;
    }
}
