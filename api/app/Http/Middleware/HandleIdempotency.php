<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Platform\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CLAUDE.md §7 / §9: `Idempotency-Key` is required on booking creation
 * (and, eventually, payment intent creation). Replaying the same key for
 * the same endpoint returns the first response verbatim instead of
 * re-running the write.
 */
class HandleIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (empty($key)) {
            return response()->json([
                'message' => 'The Idempotency-Key header is required for this request.',
                'code' => 'idempotency_key_required',
            ], 422);
        }

        $endpoint = $request->method().' '.$request->path();

        $existing = IdempotencyKey::query()
            ->where('key', $key)
            ->where('endpoint', $endpoint)
            ->first();

        if ($existing !== null) {
            return response()->json($existing->response_body, $existing->response_status ?? 200);
        }

        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            try {
                IdempotencyKey::create([
                    'key' => $key,
                    'user_id' => $request->user()?->id,
                    'endpoint' => $endpoint,
                    'response_status' => $response->getStatusCode(),
                    'response_body' => json_decode($response->getContent() ?: '{}', true),
                    'expires_at' => now()->addHours(24),
                ]);
            } catch (QueryException) {
                // Unique-key race: a concurrent request with the same key
                // recorded its response first. The response already
                // computed for this request is still correct to return.
            }
        }

        return $response;
    }
}
