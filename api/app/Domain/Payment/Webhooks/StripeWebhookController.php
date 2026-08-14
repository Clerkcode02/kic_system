<?php

declare(strict_types=1);

namespace App\Domain\Payment\Webhooks;

use App\Domain\Payment\Actions\IngestStripeWebhookEvent;
use App\Domain\Payment\Jobs\ProcessStripeWebhookJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * CLAUDE.md §7/§14: unauthenticated (Stripe, not a logged-in user, calls
 * this), signature-verified, and durable — the raw event is written to
 * stripe_events (deduped by event.id) before a queued job does the actual
 * work, and this always returns 200 once that's recorded so Stripe doesn't
 * treat a slow downstream handler as a delivery failure and retry.
 */
class StripeWebhookController extends Controller
{
    public function __construct(private readonly IngestStripeWebhookEvent $ingest)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
                (string) config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response()->json(['message' => 'Invalid Stripe signature.'], 400);
        }

        if ($this->ingest->handle($event)) {
            ProcessStripeWebhookJob::dispatch($event->id);
        }

        return response()->json(['received' => true]);
    }
}
