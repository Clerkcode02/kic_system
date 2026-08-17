<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Events\RepeatedFailedLoginsDetected;
use App\Domain\User\Models\FailedLoginAttempt;
use App\Support\Facades\Settings;
use Illuminate\Http\Request;

/**
 * SRS §17: "anomaly alerts on repeated failed logins." Records every
 * bad-credential attempt and dispatches RepeatedFailedLoginsDetected once
 * the count for that email crosses a threshold within a rolling window.
 *
 * Debounce choice: rather than a cache-based cooldown, this fires only on
 * exact multiples of the threshold (attempt #5, #10, #15, ...) within the
 * window. That's deterministic (no Redis/cache dependency, trivially unit
 * testable) and still guarantees re-alerting if the attacker keeps going
 * instead of firing once and going silent.
 */
final class FailedLoginMonitor
{
    private const DEFAULT_THRESHOLD = 5;

    private const DEFAULT_WINDOW_MINUTES = 15;

    public function record(Request $request, string $email): void
    {
        $attempt = FailedLoginAttempt::create([
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $threshold = $this->threshold();
        $windowMinutes = $this->windowMinutes();

        $recentCount = FailedLoginAttempt::query()
            ->where('email', $email)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        if ($recentCount >= $threshold && $recentCount % $threshold === 0) {
            RepeatedFailedLoginsDetected::dispatch($attempt, $email, $recentCount, $windowMinutes);
        }
    }

    private function threshold(): int
    {
        return (int) Settings::get('security.failed_login_threshold', self::DEFAULT_THRESHOLD);
    }

    private function windowMinutes(): int
    {
        return (int) Settings::get('security.failed_login_window_minutes', self::DEFAULT_WINDOW_MINUTES);
    }
}
