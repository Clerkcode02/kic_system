<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\RefundStatus;
use App\Domain\Payment\Events\RefundRateSpikeDetected;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Refund;
use App\Support\Facades\Settings;
use Illuminate\Support\Facades\Cache;

/**
 * SRS §17: "anomaly alerts on ... spike in refund rate." Compares a
 * rolling 24h refund rate (succeeded refunds / succeeded payments) against
 * a configurable threshold every time a refund succeeds.
 *
 * Debounce: unlike the failed-login counter (a clean per-email modulo),
 * a rate can hover above threshold across many consecutive refunds, so a
 * count-based debounce doesn't fit. Instead this takes a cache-based
 * cooldown lock (`Cache::add`, atomic) — once fired, it won't fire again
 * for `security.refund_rate_spike_cooldown_minutes` (default 60) even if
 * the rate stays elevated.
 *
 * `security.refund_rate_min_sample` (default 5) guards against noise on a
 * near-empty platform, where e.g. 1 refund / 2 payments = 50% is not a
 * meaningful signal.
 */
final class RefundRateMonitor
{
    private const DEFAULT_THRESHOLD = 0.10;

    private const DEFAULT_MIN_SAMPLE = 5;

    private const DEFAULT_WINDOW_HOURS = 24;

    private const DEFAULT_COOLDOWN_MINUTES = 60;

    private const COOLDOWN_CACHE_KEY = 'security:refund_rate_spike_alert_lock';

    public function checkAndAlert(Refund $refund): void
    {
        $windowHours = $this->windowHours();
        $since = now()->subHours($windowHours);

        $paymentCount = Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->where('created_at', '>=', $since)
            ->count();

        $minSample = $this->minSample();

        if ($paymentCount < $minSample) {
            return;
        }

        $refundCount = Refund::query()
            ->where('status', RefundStatus::Succeeded)
            ->where('created_at', '>=', $since)
            ->count();

        $rate = $refundCount / $paymentCount;
        $threshold = $this->threshold();

        if ($rate <= $threshold) {
            return;
        }

        $cooldownMinutes = $this->cooldownMinutes();

        // Cache::add is atomic — only the caller that actually creates the
        // key gets true, so concurrent refunds can't both slip through.
        $acquired = Cache::add(self::COOLDOWN_CACHE_KEY, true, now()->addMinutes($cooldownMinutes));

        if (! $acquired) {
            return;
        }

        RefundRateSpikeDetected::dispatch($refund, $rate, $refundCount, $paymentCount, $windowHours);
    }

    private function threshold(): float
    {
        return (float) Settings::get('security.refund_rate_spike_threshold', self::DEFAULT_THRESHOLD);
    }

    private function minSample(): int
    {
        return (int) Settings::get('security.refund_rate_min_sample', self::DEFAULT_MIN_SAMPLE);
    }

    private function windowHours(): int
    {
        return (int) Settings::get('security.refund_rate_window_hours', self::DEFAULT_WINDOW_HOURS);
    }

    private function cooldownMinutes(): int
    {
        return (int) Settings::get('security.refund_rate_spike_cooldown_minutes', self::DEFAULT_COOLDOWN_MINUTES);
    }
}
