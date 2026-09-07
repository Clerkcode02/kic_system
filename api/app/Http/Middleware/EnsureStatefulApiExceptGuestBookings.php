<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class EnsureStatefulApiExceptGuestBookings extends EnsureFrontendRequestsAreStateful
{
    public function handle($request, $next)
    {
        if ($request instanceof Request && $request->is('api/v1/guest/*')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
