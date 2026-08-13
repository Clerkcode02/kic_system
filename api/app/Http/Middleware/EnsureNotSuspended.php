<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\User\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->status === UserStatus::Suspended) {
            return response()->json([
                'message' => 'This account has been suspended.',
                'code' => 'account_suspended',
            ], 403);
        }

        return $next($request);
    }
}
