<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Payment\Actions\IssueRefund;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Notifications\RefundRateSpikeDetectedNotification;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use App\Support\ValueObjects\Money;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->seed(RoleAndPermissionSeeder::class);
});

/**
 * SRS §17: "anomaly alerts on ... spike in refund rate." End-to-end
 * through the real IssueRefund action: refund #2 pushes the rolling 24h
 * rate over the 10% default threshold (2/10) -> RefundRateSpikeDetected ->
 * NotifyAdminsOfRefundRateSpike -> RecordAuditEntry.
 */
it('notifies admins and audits once refunds push the rolling 24h rate over threshold', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);

    // 20 succeeded payments so the rate stays under 10% after the first
    // refund (1/19 ≈ 5.3%) but crosses it after the second (2/18 ≈ 11.1%).
    $payments = Payment::factory()->succeeded()->count(20)->create();

    $action = app(IssueRefund::class);
    $action->handle($payments[0], $admin, Money::fromDecimal('50.00', 'CAD'), 'anomaly test refund 1');

    expect(
        DatabaseNotification::query()
            ->where('type', RefundRateSpikeDetectedNotification::class)
            ->count()
    )->toBe(0);

    $action->handle($payments[1], $admin, Money::fromDecimal('50.00', 'CAD'), 'anomaly test refund 2');

    expect(
        DatabaseNotification::query()
            ->where('notifiable_id', $admin->id)
            ->where('type', RefundRateSpikeDetectedNotification::class)
            ->count()
    )->toBe(1);

    expect(AuditLog::query()->where('action', 'payment.refund_rate_spike_detected')->count())->toBe(1);
});

it('does not notify admins while the refund rate stays under threshold', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);

    $payments = Payment::factory()->succeeded()->count(20)->create();

    app(IssueRefund::class)->handle($payments[0], $admin, Money::fromDecimal('50.00', 'CAD'), 'single refund');

    expect(
        DatabaseNotification::query()
            ->where('type', RefundRateSpikeDetectedNotification::class)
            ->count()
    )->toBe(0);
});
