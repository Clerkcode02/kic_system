<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Events;

use App\Domain\Freelance\Models\Contract;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContractMilestonesDefined implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Contract $contract,
        public readonly User $actor,
    ) {
    }
}
