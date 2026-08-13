<?php

declare(strict_types=1);

namespace App\Domain\Business\Actions;

use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Business\Events\BusinessSubmittedForVerification;
use App\Domain\Business\Models\Business;
use App\Support\Action;
use App\Support\ConflictException;

class SubmitForVerification implements Action
{
    public function handle(Business $business): Business
    {
        if ($business->verification_status === BusinessVerificationStatus::Verified) {
            throw new ConflictException('This business is already verified.', 'business_already_verified');
        }

        $business->update(['verification_status' => BusinessVerificationStatus::Pending]);

        BusinessSubmittedForVerification::dispatch($business);

        return $business;
    }
}
