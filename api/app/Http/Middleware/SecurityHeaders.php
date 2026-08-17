<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline web-hardening headers for every API response (SRS §17). This is a
 * pure JSON API consumed by a separately-hosted SPA — no HTML is rendered
 * here, so deliberately no Content-Security-Policy header: a CSP on a JSON
 * API is either a no-op (nothing here executes as HTML/JS) or actively
 * wrong (guessed directives that don't match the SPA's real asset origins,
 * which is the SPA's own concern, not this API's).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Never force HSTS over plain HTTP — local dev and CI run on HTTP
        // and browsers would otherwise remember an upgrade-to-HTTPS policy
        // that breaks the next plain-HTTP request to the same host.
        if ($request->secure() || app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
