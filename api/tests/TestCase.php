<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Payment\Services\PaymentGateway;
use App\Domain\Payment\Services\StubPaymentGateway;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Every test gets the Phase 5 stub by default, so a test unrelated
        // to payments never trips a real (or even mocked) Stripe call just
        // by walking through AcceptQuotation/HireFreelancer. Tests that
        // specifically exercise Stripe Connect rebind PaymentGateway (or
        // StripeClient) themselves.
        $this->app->bind(PaymentGateway::class, StubPaymentGateway::class);

        // The `array` cache driver (phpunit.xml's CACHE_STORE=array) lives
        // for the whole PHP process, not per-test — unlike Redis in every
        // real environment. Without this, a value an earlier test cached
        // (e.g. CategoryTreeCache::remember()) survives RefreshDatabase and
        // leaks stale data into a later test that expects a clean DB.
        Cache::flush();

        // `pgsql_read` (config/database.php — SRS §18's reporting
        // read-connection, same DB as the default connection unless
        // DB_READ_* env vars point it at an actual replica) is a genuinely
        // separate PDO/session. That's correct — a real replica has lag —
        // but it means a query on `pgsql_read` can never see a
        // RefreshDatabase test's writes, which live in an uncommitted
        // transaction on the *other* connection; no amount of also
        // wrapping `pgsql_read` in its own transaction fixes that, since
        // two Postgres sessions never see each other's uncommitted rows.
        // Test-only: alias `pgsql_read` to literally the same Connection
        // instance as the default, so reporting-query tests exercise real
        // query logic without fighting connection isolation. Production
        // code is untouched — this never runs outside `testing`.
        if ($this->app->environment('testing')) {
            DB::extend('pgsql_read', fn () => DB::connection());
        }
    }
}
