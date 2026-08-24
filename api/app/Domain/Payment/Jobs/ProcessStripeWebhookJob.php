<?php

declare(strict_types=1);

namespace App\Domain\Payment\Jobs;

use App\Domain\Payment\Actions\CapturePayment;
use App\Domain\Payment\Actions\MarkPaymentFailed;
use App\Domain\Payment\Actions\ReconcileTransfer;
use App\Domain\Payment\Actions\RecordChargeDispute;
use App\Domain\Payment\Actions\RecordChargeRefund;
use App\Domain\Payment\Actions\SyncConnectAccountStatus;
use App\Domain\Payment\Models\StripeEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Sentry\State\Scope;

use function Sentry\withScope;

use Throwable;

/**
 * CLAUDE.md §7/§14: dispatched on the `payments` queue by
 * StripeWebhookController after the raw event is durably recorded — this
 * job can crash mid-processing and simply retries against the same
 * `stripe_events` row, which is what makes the whole pipeline replay-safe.
 * `processed_at` is the idempotency guard: once set, a redelivered event
 * (same Stripe event id, e.g. Stripe's own retry after a slow 200) is a
 * pure no-op. Each individual handler is additionally idempotent on the
 * underlying intent/transfer id, for the rarer case of two distinct event
 * ids describing the same underlying object.
 */
final class ProcessStripeWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $stripeEventId)
    {
        $this->onQueue('payments');
    }

    public function handle(
        CapturePayment $capturePayment,
        MarkPaymentFailed $markPaymentFailed,
        RecordChargeRefund $recordChargeRefund,
        RecordChargeDispute $recordChargeDispute,
        ReconcileTransfer $reconcileTransfer,
        SyncConnectAccountStatus $syncConnectAccountStatus,
    ): void {
        $event = StripeEvent::query()->where('stripe_event_id', $this->stripeEventId)->first();

        if ($event === null || $event->processed_at !== null) {
            return;
        }

        $object = $event->payload['data']['object'] ?? [];

        match ($event->type) {
            'payment_intent.succeeded' => $capturePayment->handle((string) ($object['id'] ?? '')),
            'payment_intent.payment_failed' => $markPaymentFailed->handle((string) ($object['id'] ?? '')),
            'charge.refunded' => $recordChargeRefund->handle((string) ($object['payment_intent'] ?? '')),
            'charge.dispute.created' => $recordChargeDispute->handle(
                (string) ($object['payment_intent'] ?? ''),
                (string) ($object['reason'] ?? 'unknown'),
            ),
            'transfer.paid' => $reconcileTransfer->handle((string) ($object['id'] ?? ''), succeeded: true),
            'transfer.failed' => $reconcileTransfer->handle((string) ($object['id'] ?? ''), succeeded: false),
            'account.updated' => $syncConnectAccountStatus->handle(
                (string) ($object['id'] ?? ''),
                (bool) ($object['charges_enabled'] ?? false),
                (bool) ($object['payouts_enabled'] ?? false),
            ),
            default => null,
        };

        $event->update(['processed_at' => now()]);
    }

    /**
     * SRS §21 deployment prompt: "Alert on Stripe webhook processing
     * failures specifically" — a plain uncaught exception here would still
     * reach Sentry via the queue worker's default failed-job reporting,
     * but with no way to distinguish it from any other job failure. Tagging
     * it lets alert rules target this specific, payment-critical failure
     * mode instead of firing on every noisy background job retry.
     */
    public function failed(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        withScope(function (Scope $scope) use ($exception): void {
            $scope->setTag('alert', 'stripe-webhook-processing-failure');
            $scope->setContext('stripe_event', ['stripe_event_id' => $this->stripeEventId]);

            report($exception);
        });
    }
}
