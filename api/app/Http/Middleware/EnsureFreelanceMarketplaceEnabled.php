<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * SRS §21 / CLAUDE.md §12 deployment prompt: the freelance marketplace
 * (projects, proposals, contracts, milestones) sits behind the
 * `freelance-marketplace` Pennant flag defined in AppServiceProvider, so it
 * can be enabled gradually (e.g. per-region) without a deploy. While
 * disabled, every route in this group 404s rather than 403s — same
 * reasoning as the guest booking token surface (CLAUDE.md §5): the API
 * should not confirm the existence of a feature a caller isn't meant to
 * see yet.
 */
class EnsureFreelanceMarketplaceEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Feature::active('freelance-marketplace')) {
            abort(404);
        }

        return $next($request);
    }
}
