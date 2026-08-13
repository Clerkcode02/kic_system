<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\TransientToken;

class LogoutCurrentDevice
{
    /**
     * @param  User  $user  Must be the authenticated request's user, resolved
     *                      before this runs so `currentAccessToken()` is set.
     */
    public function handle(Request $request, User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof TransientToken) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return;
        }

        $token?->delete();
    }
}
