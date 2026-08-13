<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Models\User;
use Illuminate\Auth\Events\Verified;

class VerifyEmail
{
    public function handle(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'status' => UserStatus::Active,
        ])->save();

        event(new Verified($user));
    }
}
