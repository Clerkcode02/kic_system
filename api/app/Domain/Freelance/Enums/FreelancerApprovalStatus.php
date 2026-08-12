<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Enums;

enum FreelancerApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
