<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Platform\Models\IdempotencyKey;
use App\Support\ValueObjects\BookingActor;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CLAUDE.md §7 / §9: `Idempotency-Key` is required on booking creation and
 * payment intent creation. Replaying the same key for the same endpoint
 * returns the first response verbatim instead of re-running the write.
 *
 * Keys are unique per **(key, scope, endpoint)**, where scope is the acting
 * identity (SRS §6.1). Guests choose their own keys with no account behind
 * them, so a globally-unique key column would let one guest's key collide
 * with another's — the second caller would silently receive the first
 * caller's booking. Scoping makes that impossible.
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
        $scope = $this->scope($request);

        $existing = IdempotencyKey::query()
            ->where('key', $key)
            ->where('scope', $scope)
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
                    'scope' => $scope,
                    'user_id' => $request->user('sanctum')?->id,
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

    /**
     * The identity a key is unique within. Resolved from the most
     * authoritative source available, in order.
     */
    private function scope(Request $request): string
    {
        // Guest routes: ResolveBookingActor already established the actor
        // from the booking access token.
        $resolved = $request->attributes->get(ResolveBookingActor::ACTOR_ATTRIBUTE);

        if ($resolved instanceof BookingActor) {
            return $resolved->idempotencyScope();
        }

        $user = $request->user('sanctum');

        if ($user !== null) {
            return 'user:'.$user->id;
        }

        // Guest booking creation: the actor doesn't exist yet, but the
        // submitted email is the identity the booking will be created
        // under, so it is the correct scope.
        $email = $request->input('guest_email');

        if (is_string($email) && trim($email) !== '') {
            return 'guest:'.hash('sha256', BookingActor::normalizeEmail($email));
        }

        // Last resort so an anonymous caller still gets replay protection
        // rather than sharing the global namespace with everyone else.
        return 'ip:'.hash('sha256', (string) $request->ip());
    }
}
