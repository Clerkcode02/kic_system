<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\ProposalStatus;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\User\Models\User;
use App\Support\Action;

final class ShortlistProposal implements Action
{
    public function __construct(private readonly TransitionProposalStatus $transition)
    {
    }

    public function handle(Proposal $proposal, User $actor): Proposal
    {
        return $this->transition->handle($proposal, ProposalStatus::Shortlisted, $actor, 'Shortlisted by client.');
    }
}
