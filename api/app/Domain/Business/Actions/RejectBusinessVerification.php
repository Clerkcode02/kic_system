<?php

declare(strict_types=1);

namespace App\Domain\Business\Actions;

use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Business\Events\BusinessVerificationRejected;
use App\Domain\Business\Models\Business;
use App\Support\Action;
use App\Support\ConflictException;

final class RejectBusinessVerification implements Action
{
    public function handle(Business $business, ?string $reason = null): Business
    {
        if ($business->verification_status !== BusinessVerificationStatus::Pending) {
            throw new ConflictException('Only a pending business can be rejected.', 'business_not_pending');
        }

        $business->update(['verification_status' => BusinessVerificationStatus::Rejected]);

        BusinessVerificationRejected::dispatch($business, $reason);

        return $business;
    }
}
