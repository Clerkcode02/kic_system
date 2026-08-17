<?php

declare(strict_types=1);

namespace App\Domain\Payment\Jobs;

use App\Domain\Booking\Models\Booking;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PayoutStatus;
use App\Domain\Payment\Events\PayoutCompleted;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Payout;
use App\Domain\Payment\Services\PayoutAnomalyDetector;
use App\Support\ValueObjects\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * CLAUDE.md §7/§8 item 6: nightly ledger sweep on the `payouts` queue.
 * Booking payments are destination charges (CLAUDE.md §5) — funds already
 * landed directly on the provider's connected account at charge time, so
 * this never re-moves money via Stripe. It only groups every succeeded
 * booking Payment not yet attached to a Payout into one Payout row per
 * provider, so GET /providers/me/earnings has a stable ledger to read.
 * Reruns are safe: `payments.payout_id` is set inside the same transaction
 * that creates the Payout, so an already-swept payment is never picked up
 * by a later run.
 */
final class RunProviderPayoutJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('payouts');
    }

    public function handle(PayoutAnomalyDetector $anomalyDetector): void
    {
        $unswept = Payment::query()
            ->where('payable_type', 'booking')
            ->where('status', PaymentStatus::Succeeded)
            ->whereNull('payout_id')
            ->with('payable')
            ->get()
            ->filter(fn (Payment $payment) => $payment->payable instanceof Booking)
            ->groupBy(function (Payment $payment): string {
                /** @var Booking $booking */
                $booking = $payment->payable;

                return $booking->provider_id;
            });

        foreach ($unswept as $providerId => $payments) {
            $payout = DB::transaction(function () use ($providerId, $payments) {
                $total = $payments->reduce(
                    fn (?Money $carry, Payment $p) => $carry === null ? $p->provider_net_amount : $carry->add($p->provider_net_amount),
                    null,
                );

                if ($total === null || $total->isZero()) {
                    return null;
                }

                $payout = Payout::create([
                    'provider_id' => $providerId,
                    'amount' => $total,
                    'currency' => 'CAD',
                    'stripe_transfer_id' => null,
                    'status' => PayoutStatus::Paid,
                ]);

                Payment::query()
                    ->whereIn('id', $payments->pluck('id'))
                    ->update(['payout_id' => $payout->id]);

                PayoutCompleted::dispatch($payout);

                return $payout;
            });

            // Anomaly detection reads other Paid payouts for this provider,
            // so it runs after the transaction commits rather than inside
            // it (SRS §17 "unusual payout patterns" is best-effort alerting,
            // not a gate on the payout itself).
            if ($payout !== null) {
                $anomalyDetector->detect($payout);
            }
        }
    }
}
