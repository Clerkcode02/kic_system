<?php

declare(strict_types=1);

namespace App\Support\HealthCheck;

use App\Support\Mail\GmailApiTransport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Stripe\StripeClient;
use Throwable;

/**
 * Backs HealthController — kept out of the controller itself so the
 * DB/Redis/Storage calls below don't trip the "controllers don't access
 * the database directly" architecture rule (tests/Architecture/
 * ControllersTest.php); the controller only calls handle() and shapes the
 * JSON response.
 */
final class SystemHealthCheck
{
    private const EXTERNAL_CHECK_TTL_SECONDS = 15;

    /**
     * @return array<string, array{status: string, latency_ms: int, error?: string}>
     */
    public function handle(): array
    {
        return [
            'database' => $this->timed(fn () => $this->checkDatabase()),
            'redis' => $this->timed(fn () => $this->checkRedis()),
            'storage' => $this->cached('health_check_storage', fn () => $this->checkStorage()),
            'stripe' => $this->cached('health_check_stripe', fn () => $this->checkStripe()),
            'gmail' => $this->cached('health_check_gmail', fn () => $this->checkGmail()),
        ];
    }

    /**
     * @param  callable(): void  $check
     * @return array{status: string, latency_ms: int, error?: string}
     */
    private function timed(callable $check): array
    {
        $start = microtime(true);

        try {
            $check();

            return ['status' => 'ok', 'latency_ms' => $this->elapsedMs($start)];
        } catch (Throwable $e) {
            return ['status' => 'error', 'latency_ms' => $this->elapsedMs($start), 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  callable(): void  $check
     * @return array{status: string, latency_ms: int, error?: string}
     */
    private function cached(string $key, callable $check): array
    {
        return Cache::remember($key, self::EXTERNAL_CHECK_TTL_SECONDS, fn () => $this->timed($check));
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }

    private function checkDatabase(): void
    {
        DB::select('select 1');
    }

    private function checkRedis(): void
    {
        Redis::connection()->ping();
    }

    private function checkStorage(): void
    {
        // Cheap existence probe against the configured disk root — proves
        // credentials and endpoint reachability without writing anything.
        Storage::disk('s3')->exists('.health-check');
    }

    private function checkStripe(): void
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '') {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        (new StripeClient($secret))->balance->retrieve();
    }

    private function checkGmail(): void
    {
        if (! app()->environment('production', 'staging')) {
            return;
        }

        // Not container-bound (MailServiceProvider only ever builds it
        // inside the Mail::extend('gmail', ...) closure Symfony's mailer
        // manager calls), so it's constructed directly here the same way.
        (new GmailApiTransport(
            (string) config('services.gmail.client_id'),
            (string) config('services.gmail.client_secret'),
            (string) config('services.gmail.refresh_token'),
        ))->checkTokenReachable();
    }
}
