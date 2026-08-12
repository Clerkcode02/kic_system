<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Enums;

enum ProposalStatus: string
{
    case Submitted = 'submitted';
    case Shortlisted = 'shortlisted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}
