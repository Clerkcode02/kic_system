<?php

declare(strict_types=1);

use App\Domain\Business\Models\Business;
use App\Domain\Payment\Events\PayoutAnomalyDetected;
use App\Domain\Payment\Models\Payout;
use App\Domain\Payment\Services\PayoutAnomalyDetector;
use App\Support\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('does not flag a payout in line with the provider trailing average', function () {
    Event::fake([PayoutAnomalyDetected::class]);

    $business = Business::factory()->create();

    Payout::factory()->paid()->create([
        'provider_id' => $business->id,
        'amount' => Money::fromDecimal('100.00', 'CAD'),
        'created_at' => now()->subDays(5),
    ]);

    $payout = Payout::factory()->paid()->create([
        'provider_id' => $business->id,
        'amount' => Money::fromDecimal('120.00', 'CAD'),
    ]);

    (new PayoutAnomalyDetector())->detect($payout);

    Event::assertNotDispatched(PayoutAnomalyDetected::class);
});

it('flags a payout more than 3x the provider trailing-30-day average', function () {
    Event::fake([PayoutAnomalyDetected::class]);

    $business = Business::factory()->create();

    Payout::factory()->paid()->create([
        'provider_id' => $business->id,
        'amount' => Money::fromDecimal('100.00', 'CAD'),
        'created_at' => now()->subDays(5),
    ]);

    $payout = Payout::factory()->paid()->create([
        'provider_id' => $business->id,
        'amount' => Money::fromDecimal('500.00', 'CAD'),
    ]);

    (new PayoutAnomalyDetector())->detect($payout);

    Event::assertDispatchedTimes(PayoutAnomalyDetected::class, 1);
    Event::assertDispatched(function (PayoutAnomalyDetected $event) use ($payout) {
        return $event->payout->is($payout)
            && $event->reason === PayoutAnomalyDetector::REASON_TRAILING_AVERAGE_EXCEEDED;
    });
});

it('ignores payouts from a different provider when computing the trailing average', function () {
    Event::fake([PayoutAnomalyDetected::class]);

    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();

    // A huge payout for a different provider must not suppress or affect
    // this provider's own (empty) trailing average.
    Payout::factory()->paid()->create([
        'provider_id' => $otherBusiness->id,
        'amount' => Money::fromDecimal('10000.00', 'CAD'),
        'created_at' => now()->subDays(2),
    ]);

    $payout = Payout::factory()->paid()->create([
        'provider_id' => $business->id,
        'amount' => Money::fromDecimal('2500.01', 'CAD'),
    ]);

    (new PayoutAnomalyDetector())->detect($payout);

    Event::assertDispatchedTimes(PayoutAnomalyDetected::class, 1);
    Event::assertDispatched(function (PayoutAnomalyDetected $event) use ($payout) {
        return $event->payout->is($payout)
            && $event->reason === PayoutAnomalyDetector::REASON_FIRST_PAYOUT_ABOVE_THRESHOLD;
    });
});

it('flags a first-ever payout above the absolute threshold', function () {
    Event::fake([PayoutAnomalyDetected::class]);

    $business = Business::factory()->create();

    $payout = Payout::factory()->paid()->create([
        'provider_id' => $business->id,
        'amount' => Money::fromDecimal('2500.00', 'CAD'),
    ]);

    (new PayoutAnomalyDetector())->detect($payout);

    Event::assertDispatchedTimes(PayoutAnomalyDetected::class, 1);
    Event::assertDispatched(function (PayoutAnomalyDetected $event) use ($payout) {
        return $event->payout->is($payout)
            && $event->reason === PayoutAnomalyDetector::REASON_FIRST_PAYOUT_ABOVE_THRESHOLD;
    });
});

it('does not flag a first-ever payout below the absolute threshold', function () {
    Event::fake([PayoutAnomalyDetected::class]);

    $business = Business::factory()->create();

    $payout = Payout::factory()->paid()->create([
        'provider_id' => $business->id,
        'amount' => Money::fromDecimal('500.00', 'CAD'),
    ]);

    (new PayoutAnomalyDetector())->detect($payout);

    Event::assertNotDispatched(PayoutAnomalyDetected::class);
});

it('ignores payouts older than 30 days when computing the trailing average', function () {
    Event::fake([PayoutAnomalyDetected::class]);

    $business = Business::factory()->create();

    // Stale payout would pull the average up if counted, which would mask
    // the anomaly below — must be excluded from the 30-day window.
    Payout::factory()->paid()->create([
        'provider_id' => $business->id,
        'amount' => Money::fromDecimal('900.00', 'CAD'),
        'created_at' => now()->subDays(45),
    ]);

    $payout = Payout::factory()->paid()->create([
        'provider_id' => $business->id,
        'amount' => Money::fromDecimal('2500.00', 'CAD'),
    ]);

    (new PayoutAnomalyDetector())->detect($payout);

    // No prior payout within 30 days => treated as a first-ever payout,
    // and 2500 > the $2000 default threshold.
    Event::assertDispatchedTimes(PayoutAnomalyDetected::class, 1);
    Event::assertDispatched(function (PayoutAnomalyDetected $event) use ($payout) {
        return $event->payout->is($payout)
            && $event->reason === PayoutAnomalyDetector::REASON_FIRST_PAYOUT_ABOVE_THRESHOLD;
    });
});
