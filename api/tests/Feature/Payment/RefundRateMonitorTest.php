<?php

declare(strict_types=1);

use App\Domain\Payment\Events\RefundRateSpikeDetected;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Refund;
use App\Domain\Payment\Services\RefundRateMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/**
 * Refund::factory()'s default 'payment_id' => Payment::factory() would
 * silently create an extra succeeded Payment dated "now" for every Refund
 * row made in these tests, contaminating the rolling-window payment count
 * these tests are trying to pin exactly — so every Refund here is attached
 * to an explicit, already-created Payment via for() instead.
 */
it('does not alert when the refund rate is under the threshold', function () {
    Event::fake([RefundRateSpikeDetected::class]);

    $payments = Payment::factory()->succeeded()->count(10)->create();
    $refund = Refund::factory()->succeeded()->for($payments[0], 'payment')->create();

    (new RefundRateMonitor())->checkAndAlert($refund);

    // 1 refund / 10 payments = 10%, not strictly over the 10% threshold.
    Event::assertNotDispatched(RefundRateSpikeDetected::class);
});

it('alerts once the rolling 24h refund rate crosses the threshold', function () {
    Event::fake([RefundRateSpikeDetected::class]);

    $payments = Payment::factory()->succeeded()->count(10)->create();
    Refund::factory()->succeeded()->for($payments[0], 'payment')->create();
    $refund = Refund::factory()->succeeded()->for($payments[1], 'payment')->create();

    (new RefundRateMonitor())->checkAndAlert($refund);

    // 2 refunds / 10 payments = 20% > 10% default threshold.
    Event::assertDispatchedTimes(RefundRateSpikeDetected::class, 1);
    Event::assertDispatched(function (RefundRateSpikeDetected $event) use ($refund) {
        return $event->triggeringRefund->is($refund)
            && $event->refundCount === 2
            && $event->paymentCount === 10;
    });
});

it('does not alert below the minimum sample size even at 100% refund rate', function () {
    Event::fake([RefundRateSpikeDetected::class]);

    $payments = Payment::factory()->succeeded()->count(2)->create();
    $refund = Refund::factory()->succeeded()->for($payments[0], 'payment')->create();

    (new RefundRateMonitor())->checkAndAlert($refund);

    Event::assertNotDispatched(RefundRateSpikeDetected::class);
});

it('debounces repeated alerts within the cooldown window', function () {
    Event::fake([RefundRateSpikeDetected::class]);

    $payments = Payment::factory()->succeeded()->count(10)->create();
    Refund::factory()->succeeded()->for($payments[0], 'payment')->create();
    Refund::factory()->succeeded()->for($payments[1], 'payment')->create();

    $monitor = new RefundRateMonitor();

    $firstRefund = Refund::factory()->succeeded()->for($payments[2], 'payment')->create();
    $monitor->checkAndAlert($firstRefund);

    $secondRefund = Refund::factory()->succeeded()->for($payments[3], 'payment')->create();
    $monitor->checkAndAlert($secondRefund);

    // Still above threshold on the second call (4/10 = 40%), but the
    // cooldown lock should suppress the second dispatch.
    Event::assertDispatchedTimes(RefundRateSpikeDetected::class, 1);
});

it('excludes refunds and payments outside the rolling window', function () {
    Event::fake([RefundRateSpikeDetected::class]);

    $stalePayments = Payment::factory()->succeeded()->count(10)->create(['created_at' => now()->subDays(3)]);

    foreach ($stalePayments->take(5) as $stalePayment) {
        Refund::factory()->succeeded()->for($stalePayment, 'payment')->create(['created_at' => now()->subDays(3)]);
    }

    // The triggering refund itself is fresh, but reuses a stale Payment —
    // no *Payment* row is dated within the last 24h, so the window has
    // nothing to sample from regardless of the refund's own timestamp.
    $refund = Refund::factory()->succeeded()->for($stalePayments[9], 'payment')->create();

    (new RefundRateMonitor())->checkAndAlert($refund);

    Event::assertNotDispatched(RefundRateSpikeDetected::class);
});
