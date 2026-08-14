<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Events;

use App\Domain\Freelance\Models\FreelancerProfile;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FreelancerVerificationRejected implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FreelancerProfile $profile,
        public readonly ?string $reason,
    ) {
    }
}
