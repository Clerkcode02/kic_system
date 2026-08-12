<?php

declare(strict_types=1);

namespace App\Domain\User\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Provider = 'provider';
    case Freelancer = 'freelancer';
    case Admin = 'admin';
}
