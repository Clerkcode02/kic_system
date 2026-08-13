<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasAnyRole($roles)) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
                'code' => 'insufficient_role',
            ], 403);
        }

        return $next($request);
    }
}
