<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Booking\Models\Booking;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Models\User;
use App\Policies\Concerns\GrantsAdminOversight;

class PaymentPolicy
{
    use GrantsAdminOversight;

    public function view(User $user, Payment $payment): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $user->can(PermissionName::PaymentsView->value);
        }

        return $user->can(PermissionName::PaymentsView->value) && $this->isPartyToPayable($user, $payment);
    }

    public function create(User $user, Booking $booking): bool
    {
        return $user->can(PermissionName::PaymentsCreate->value) && $booking->customer_id === $user->id;
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->can(PermissionName::PaymentsRefund->value);
    }

    private function isPartyToPayable(User $user, Payment $payment): bool
    {
        $payable = $payment->payable;

        return match (true) {
            $payable instanceof Booking => $payable->customer_id === $user->id || $payable->provider->user_id === $user->id,
            $payable instanceof Milestone => $payable->contract->project->client_id === $user->id
                || $payable->contract->proposal->freelancer->user_id === $user->id,
            default => false,
        };
    }
}
