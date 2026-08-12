<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Enums;

enum ContractStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Terminated = 'terminated';
}
