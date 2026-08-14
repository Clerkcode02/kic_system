<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Events;

use App\Domain\Freelance\Models\Proposal;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProposalStatusChanged implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Proposal $proposal,
        public readonly string $fromStatus,
        public readonly string $toStatus,
        public readonly ?User $actor,
    ) {
    }
}
