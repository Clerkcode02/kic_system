<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * The single place that decides whether a client gets a session cookie or a
 * bearer token — no controller or Action knows which mode is active
 * (CLAUDE.md §9.2). Stateful origins (the SPA's `stateful` domains, see
 * config/sanctum.php) get a `Set-Cookie` via the session guard; every other
 * caller (mobile, Postman, server-to-server) gets a device-named personal
 * access token instead.
 */
class IssuesAuthCredentials
{
    /**
     * @return array{mode: 'cookie'|'token', user: User, token: string|null}
     */
    public function issue(Request $request, User $user): array
    {
        if (EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return ['mode' => 'cookie', 'user' => $user, 'token' => null];
        }

        $token = $user->createToken($this->deviceName($request))->plainTextToken;

        return ['mode' => 'token', 'user' => $user, 'token' => $token];
    }

    private function deviceName(Request $request): string
    {
        $label = $request->header('X-Device-Name');

        if (is_string($label) && trim($label) !== '') {
            return trim($label);
        }

        return $request->userAgent() ?: 'Unknown device';
    }
}
