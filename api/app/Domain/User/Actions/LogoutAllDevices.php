<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\TransientToken;

class LogoutAllDevices
{
    public function handle(Request $request, User $user): void
    {
        $token = $user->currentAccessToken();

        $user->tokens()->delete();

        if ($token instanceof TransientToken) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
