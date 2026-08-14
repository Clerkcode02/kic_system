<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\FreelancerApprovalStatus;
use App\Domain\Freelance\Events\FreelancerVerificationApproved;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Support\Action;
use App\Support\ConflictException;

final class ApproveFreelancerVerification implements Action
{
    public function handle(FreelancerProfile $profile): FreelancerProfile
    {
        if ($profile->approval_status !== FreelancerApprovalStatus::Pending) {
            throw new ConflictException('Only a pending freelancer profile can be approved.', 'freelancer_not_pending');
        }

        $profile->update(['approval_status' => FreelancerApprovalStatus::Approved]);

        FreelancerVerificationApproved::dispatch($profile);

        return $profile;
    }
}
