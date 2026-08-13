<?php

declare(strict_types=1);

namespace App\Domain\User\Enums;

enum RoleName: string
{
    case Customer = 'customer';
    case ProviderOwner = 'provider_owner';
    case ProviderStaff = 'provider_staff';
    case Freelancer = 'freelancer';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';
}
