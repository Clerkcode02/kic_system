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

    public function checkIn(User $user, Booking $booking): bool
    {
        return $user->can(PermissionName::BookingsManageStatus->value)
            && $booking->provider->user_id === $user->id;
    }

    public function complete(User $user, Booking $booking): bool
    {
        return $user->can(PermissionName::BookingsManageStatus->value)
            && $booking->provider->user_id === $user->id;
    }

    public function confirmCompletion(User $user, Booking $booking): bool
    {
        return $user->can(PermissionName::BookingsConfirmCompletion->value)
            && $booking->customer_id === $user->id;
    }

    /**
     * Only the customer reviews the provider (SRS §19: "provider may reply
     * once" implies a one-directional customer → provider review). Whether
     * the booking is actually completed, and the one-per-transaction rule,
     * are enforced by SubmitBookingReview — this only gates who may call it.
     */
    public function review(User $user, Booking $booking): bool
    {
        return $user->can(PermissionName::ReviewsCreate->value) && $booking->customer_id === $user->id;
    }
}
