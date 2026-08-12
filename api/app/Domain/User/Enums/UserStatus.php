<?php

declare(strict_types=1);

namespace App\Domain\User\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Pending = 'pending';
}
