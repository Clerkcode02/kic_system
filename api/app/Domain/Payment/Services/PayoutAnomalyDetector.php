<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Enums\PayoutStatus;
use App\Domain\Payment\Events\PayoutAnomalyDetected;
use App\Domain\Payment\Models\Payout;
use App\Support\Facades\Settings;

/**
 * SRS §17: "anomaly alerts on ... unusual payout patterns." A scaffold
 * heuristic, not a fraud-ML system — two simple rules, either of which
 * flags a freshly-created Payout:
 *
 *  1. Trailing-average blowout: the payout is more than
 *     `payout.anomaly_multiplier` (default 3x) the provider's average
 *     Paid payout over the last 30 days — only evaluated once the
 *     provider has at least one prior payout to average against.
 *  2. First-payout spike: the provider has no prior Paid payout at all,
 *     and this one exceeds `payout.first_payout_alert_threshold`
 *     (default $2,000 CAD) — a first payout is the highest-risk case for
 *     a fabricated/compromised connected account.
 *
 * No debounce here: each Payout row is a discrete event created once by
 * RunProviderPayoutJob, so there's nothing to rate-limit — at most one
 * PayoutAnomalyDetected per payout.
 */
final class PayoutAnomalyDetector
{
    public const REASON_TRAILING_AVERAGE_EXCEEDED = 'trailing_average_exceeded';

    public const REASON_FIRST_PAYOUT_ABOVE_THRESHOLD = 'first_payout_above_threshold';

    private const DEFAULT_MULTIPLIER = 3.0;

    private const DEFAULT_FIRST_PAYOUT_THRESHOLD = '2000.00';

    public function detect(Payout $payout): void
    {
        $priorPayouts = Payout::query()
            ->where('provider_id', $payout->provider_id)
            ->where('status', PayoutStatus::Paid)
            ->where('id', '!=', $payout->id)
            ->where('created_at', '>=', now()->subDays(30));

        $priorCount = (clone $priorPayouts)->count();
        $currentAmount = (float) $payout->amount->toDecimal();

        if ($priorCount === 0) {
            $firstPayoutThreshold = (float) Settings::get(
                'payout.first_payout_alert_threshold',
                self::DEFAULT_FIRST_PAYOUT_THRESHOLD,
            );

            if ($currentAmount > $firstPayoutThreshold) {
                PayoutAnomalyDetected::dispatch($payout, self::REASON_FIRST_PAYOUT_ABOVE_THRESHOLD, [
                    'first_payout_threshold' => $firstPayoutThreshold,
                ]);
            }

            return;
        }

        $averageAmount = (float) $priorPayouts->avg('amount');
        $multiplier = (float) Settings::get('payout.anomaly_multiplier', self::DEFAULT_MULTIPLIER);

        if ($averageAmount > 0 && $currentAmount > $averageAmount * $multiplier) {
            PayoutAnomalyDetected::dispatch($payout, self::REASON_TRAILING_AVERAGE_EXCEEDED, [
                'trailing_30_day_average' => round($averageAmount, 2),
                'multiplier' => $multiplier,
                'prior_payout_count' => $priorCount,
            ]);
        }
    }
}
