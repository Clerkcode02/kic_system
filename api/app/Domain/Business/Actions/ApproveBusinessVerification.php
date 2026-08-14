<?php

declare(strict_types=1);

namespace App\Domain\Business\Actions;

use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Business\Events\BusinessVerificationApproved;
use App\Domain\Business\Models\Business;
use App\Support\Action;
use App\Support\ConflictException;

/**
 * Admin decision on a business's verification queue entry (SRS §12 admin
 * workflow). Kept minimal — the full admin review surface (document
 * inspection, queue listing) is a separate feature; this Action is the
 * write path that flips verification_status and notifies the owner.
 */
final class ApproveBusinessVerification implements Action
{
    public function handle(Business $business): Business
    {
        if ($business->verification_status !== BusinessVerificationStatus::Pending) {
            throw new ConflictException('Only a pending business can be approved.', 'business_not_pending');
        }

        $business->update(['verification_status' => BusinessVerificationStatus::Verified]);

        BusinessVerificationApproved::dispatch($business);

        return $business;
    }
}
