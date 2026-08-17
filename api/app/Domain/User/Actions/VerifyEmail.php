<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\Booking\Actions\ClaimGuestBookings;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Models\User;
use Illuminate\Auth\Events\Verified;

class VerifyEmail
{
    public function __construct(private readonly ClaimGuestBookings $claimGuestBookings)
    {
    }

    public function handle(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'status' => UserStatus::Active,
        ])->save();

        // SRS §6.1: verification — and *only* verification — is what hands
        // a guest's bookings to an account. Registration and login
        // deliberately do not, since neither proves control of the mailbox
        // those bookings were placed under.
        $this->claimGuestBookings->handle($user);

        event(new Verified($user));
    }
}
