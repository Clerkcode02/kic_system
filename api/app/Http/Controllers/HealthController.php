<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\HealthCheck\SystemHealthCheck;
use Illuminate\Http\JsonResponse;

/**
 * SRS §21 / deployment prompt: a readiness check for the load balancer,
 * distinct from Laravel's own `/up` liveness probe (bootstrap/app.php) —
 * `/up` only proves the process is running, `/health` proves every
 * dependency the app actually needs (DB, Redis, S3, Stripe, Gmail API) is
 * reachable. Never wired behind auth: an LB health checker has no session.
 */
class HealthController extends Controller
{
    public function __invoke(SystemHealthCheck $healthCheck): JsonResponse
    {
        $checks = $healthCheck->handle();

        $healthy = collect($checks)->every(fn (array $check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}
