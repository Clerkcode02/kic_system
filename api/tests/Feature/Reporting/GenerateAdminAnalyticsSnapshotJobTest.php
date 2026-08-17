<?php

declare(strict_types=1);

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Dispute\Enums\DisputeStatus;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Payment\Models\Payment;
use App\Domain\Reporting\Jobs\GenerateAdminAnalyticsSnapshotJob;
use App\Domain\Reporting\Models\AdminAnalyticsSnapshot;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

it('persists a snapshot row with metrics computed from seeded data', function () {
    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);

    Business::factory()->pending()->create();
    FreelancerProfile::factory()->pending()->create();

    Dispute::factory()->create(['status' => DisputeStatus::Open]);
    Dispute::factory()->create(['status' => DisputeStatus::Resolved]);

    Payment::factory()->succeeded()->create([
        'amount' => \App\Support\ValueObjects\Money::fromDecimal('150.00', 'CAD'),
        'created_at' => now()->subHours(2),
    ]);

    Payment::factory()->succeeded()->create([
        'stripe_transfer_id' => 'tr_'.fake()->bothify('##########'),
        'amount' => \App\Support\ValueObjects\Money::fromDecimal('120.00', 'CAD'),
        'provider_net_amount' => \App\Support\ValueObjects\Money::fromDecimal('90.00', 'CAD'),
        'created_at' => now()->subHours(3),
    ]);

    // Factories cascade (a Payment pulls in a Booking, which pulls in a
    // pending Business), so the exact pending-verification count depends on
    // factory internals rather than the fixture set up above. Assert
    // against the same tables the query reads, not a hardcoded number.
    $expectedBookingsTotal = Booking::query()->count();
    $expectedVerificationQueueDepth = Business::query()->where('verification_status', \App\Domain\Business\Enums\BusinessVerificationStatus::Pending)->count()
        + FreelancerProfile::query()->where('approval_status', \App\Domain\Freelance\Enums\FreelancerApprovalStatus::Pending)->count();

    expect(AdminAnalyticsSnapshot::query()->count())->toBe(0);

    app(GenerateAdminAnalyticsSnapshotJob::class)->handle(app(\App\Domain\Reporting\Queries\ComputeAdminAnalyticsMetricsQuery::class));

    $snapshot = AdminAnalyticsSnapshot::query()->latest('snapshot_at')->first();

    // Same reasoning as $expectedBookingsTotal above — a customer signup
    // from an unrelated non-transactional test (see ConcurrentBookingTest /
    // ConcurrentHireTest) can already be sitting in the DB within the last
    // 24h, so assert the query's own count rather than a hardcoded "1".
    $expectedCustomerSignups24h = User::query()->role('customer')->where('created_at', '>=', now()->subDay())->count();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->metrics['bookings_total'])->toBe($expectedBookingsTotal)
        ->and($snapshot->metrics['new_signups_24h']['customer'])->toBe($expectedCustomerSignups24h)
        ->and($expectedCustomerSignups24h)->toBeGreaterThanOrEqual(1)
        ->and($snapshot->metrics['verification_queue_depth'])->toBe($expectedVerificationQueueDepth)
        ->and($expectedVerificationQueueDepth)->toBeGreaterThanOrEqual(2)
        ->and($snapshot->metrics['open_disputes'])->toBe(1)
        ->and((float) $snapshot->metrics['gmv_24h'])->toBe(270.0)
        ->and((float) $snapshot->metrics['payout_volume_24h'])->toBe(90.0);
});

it('computes a snapshot with zero deltas when this test creates no relevant activity', function () {
    // Booking/Freelance concurrency tests intentionally leak a handful of
    // committed rows outside RefreshDatabase (see ConcurrentBookingTest —
    // booking_status_history is append-only, so its winning booking can
    // never be cleaned up). This test creates nothing itself, so baseline
    // against whatever the query already reports rather than a hardcoded
    // zero, which would make this test's pass/fail depend on suite run
    // order instead of on what GenerateAdminAnalyticsSnapshotJob actually
    // does.
    $query = app(\App\Domain\Reporting\Queries\ComputeAdminAnalyticsMetricsQuery::class);
    $baseline = $query->handle();

    app(GenerateAdminAnalyticsSnapshotJob::class)->handle($query);

    $snapshot = AdminAnalyticsSnapshot::query()->latest('snapshot_at')->first();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->metrics['bookings_total'])->toBe($baseline['bookings_total'])
        ->and($snapshot->metrics['bookings_active_24h'])->toBe($baseline['bookings_active_24h'])
        ->and((float) $snapshot->metrics['gmv_24h'])->toBe(0.0)
        ->and($snapshot->metrics['new_signups_24h'])->toBe($baseline['new_signups_24h'])
        ->and($snapshot->metrics['verification_queue_depth'])->toBe($baseline['verification_queue_depth'])
        ->and($snapshot->metrics['open_disputes'])->toBe($baseline['open_disputes'])
        ->and((float) $snapshot->metrics['payout_volume_24h'])->toBe(0.0);
});

it('excludes activity older than 24 hours from the rolling metrics', function () {
    Payment::factory()->succeeded()->create([
        'amount' => \App\Support\ValueObjects\Money::fromDecimal('500.00', 'CAD'),
        'created_at' => now()->subDays(2),
    ]);

    app(GenerateAdminAnalyticsSnapshotJob::class)->handle(app(\App\Domain\Reporting\Queries\ComputeAdminAnalyticsMetricsQuery::class));

    $snapshot = AdminAnalyticsSnapshot::query()->latest('snapshot_at')->first();

    expect((float) $snapshot->metrics['gmv_24h'])->toBe(0.0);
});
