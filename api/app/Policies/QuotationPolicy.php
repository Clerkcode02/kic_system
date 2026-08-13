<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Booking\Models\Booking;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;
use App\Policies\Concerns\GrantsAdminOversight;

class QuotationPolicy
{
    use GrantsAdminOversight;

    public function view(User $user, Quotation $quotation): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $user->can(PermissionName::QuotationsView->value);
        }

        return $user->can(PermissionName::QuotationsView->value)
            && ($quotation->booking->customer_id === $user->id || $quotation->booking->provider->user_id === $user->id);
    }

    public function create(User $user, Booking $booking): bool
    {
        return $user->can(PermissionName::QuotationsCreate->value) && $booking->provider->user_id === $user->id;
    }

    public function revise(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationsRevise->value) && $quotation->booking->provider->user_id === $user->id;
    }

    public function accept(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationsAccept->value) && $quotation->booking->customer_id === $user->id;
    }

    public function reject(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationsReject->value) && $quotation->booking->customer_id === $user->id;
    }
}
