<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\User\Models\User;
use Illuminate\Validation\ValidationException;

class ResendEmailVerification
{
    public function handle(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['This email address is already verified.'],
            ]);
        }

        $user->sendEmailVerificationNotification();
    }
}
