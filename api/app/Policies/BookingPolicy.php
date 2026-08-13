<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Booking\Models\Booking;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;
use App\Policies\Concerns\GrantsAdminOversight;

class BookingPolicy
{
    use GrantsAdminOversight;

    public function view(User $user, Booking $booking): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $user->can(PermissionName::BookingsView->value);
        }

        return $user->can(PermissionName::BookingsView->value)
            && ($booking->customer_id === $user->id || $booking->provider->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::BookingsCreate->value);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->can(PermissionName::BookingsCancel->value)
            && ($booking->customer_id === $user->id || $booking->provider->user_id === $user->id);
    }
}
