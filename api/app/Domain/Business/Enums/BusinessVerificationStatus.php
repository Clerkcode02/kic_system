<?php

declare(strict_types=1);

namespace App\Domain\Business\Enums;

/**
 * Shared by businesses.verification_status and business_documents.verification_status —
 * a document's verification is part of the same admin review as the business itself.
 */
enum BusinessVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
