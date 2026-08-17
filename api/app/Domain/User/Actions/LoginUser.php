<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Models\User;
use App\Domain\User\Services\FailedLoginMonitor;
use App\Domain\User\Services\IssuesAuthCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUser
{
    public function __construct(
        private readonly IssuesAuthCredentials $credentials,
        private readonly FailedLoginMonitor $failedLoginMonitor,
    ) {
    }

    /**
     * @return array{mode: 'cookie'|'token', user: User, token: string|null}
     */
    public function handle(Request $request, string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->failedLoginMonitor->record($request, $email);

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->status === UserStatus::Suspended) {
            throw ValidationException::withMessages([
                'email' => ['This account has been suspended.'],
            ]);
        }

        return $this->credentials->issue($request, $user);
    }
}
