<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\FreelancerApprovalStatus;
use App\Domain\Freelance\Events\FreelancerVerificationRejected;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Support\Action;
use App\Support\ConflictException;

final class RejectFreelancerVerification implements Action
{
    public function handle(FreelancerProfile $profile, ?string $reason = null): FreelancerProfile
    {
        if ($profile->approval_status !== FreelancerApprovalStatus::Pending) {
            throw new ConflictException('Only a pending freelancer profile can be rejected.', 'freelancer_not_pending');
        }

        $profile->update(['approval_status' => FreelancerApprovalStatus::Rejected]);

        FreelancerVerificationRejected::dispatch($profile, $reason);

        return $profile;
    }
}
