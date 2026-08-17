<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Payment\Jobs\RunProviderPayoutJob;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Payout;
use App\Domain\Payment\Notifications\PayoutAnomalyDetectedNotification;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use App\Support\ValueObjects\Money;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function payoutSweepBooking(\App\Domain\Business\Models\Business $business, string $amount): Payment
{
    [$customer, $address] = bookingCustomer();
    $service = bookingService($business, ServicePricingType::Fixed);

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => BookingStatus::Completed,
    ]);

    return Payment::factory()->succeeded()->forBooking($booking)->create([
        'amount' => Money::fromDecimal($amount, 'CAD'),
        'platform_fee_amount' => Money::fromDecimal('0.00', 'CAD'),
        'provider_net_amount' => Money::fromDecimal($amount, 'CAD'),
    ]);
}

/**
 * SRS §17: "anomaly alerts on ... unusual payout patterns." End-to-end
 * through the real nightly sweep job: RunProviderPayoutJob creates a
 * Payout -> PayoutAnomalyDetector flags it -> PayoutAnomalyDetected ->
 * NotifyAdminsOfPayoutAnomaly -> RecordAuditEntry.
 */
it('notifies admins and audits a first payout above the absolute threshold', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);

    [, $business] = bookingProvider();
    payoutSweepBooking($business, '2500.00');

    app()->call([new RunProviderPayoutJob(), 'handle']);

    expect(Payout::query()->count())->toBe(1);

    expect(
        DatabaseNotification::query()
            ->where('notifiable_id', $admin->id)
            ->where('type', PayoutAnomalyDetectedNotification::class)
            ->count()
    )->toBe(1);

    expect(AuditLog::query()->where('action', 'payout.anomaly_detected')->count())->toBe(1);
});

it('does not notify admins for a payout within normal range', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);

    [, $business] = bookingProvider();
    payoutSweepBooking($business, '150.00');

    app()->call([new RunProviderPayoutJob(), 'handle']);

    expect(
        DatabaseNotification::query()
            ->where('type', PayoutAnomalyDetectedNotification::class)
            ->count()
    )->toBe(0);
});
