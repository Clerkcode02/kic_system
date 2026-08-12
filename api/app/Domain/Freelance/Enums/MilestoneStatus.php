<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Enums;

enum MilestoneStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Paid = 'paid';
    case Disputed = 'disputed';
}
