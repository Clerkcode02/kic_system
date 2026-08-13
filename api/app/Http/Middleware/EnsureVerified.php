<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Your email address must be verified to perform this action.',
                'code' => 'account_unverified',
            ], 403);
        }

        return $next($request);
    }
}
