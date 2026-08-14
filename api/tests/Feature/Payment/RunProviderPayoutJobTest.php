<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Payment\Jobs\RunProviderPayoutJob;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Payout;
use App\Support\ValueObjects\Money;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function succeededBookingPayment(\App\Domain\Business\Models\Business $business, string $amount): Payment
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

it('sweeps succeeded booking payments into one payout per provider', function () {
    [, $businessA] = bookingProvider();
    [, $businessB] = bookingProvider();

    succeededBookingPayment($businessA, '100.00');
    succeededBookingPayment($businessA, '50.00');
    succeededBookingPayment($businessB, '75.00');

    (new RunProviderPayoutJob())->handle();

    $payoutA = Payout::query()->where('provider_id', $businessA->id)->sole();
    $payoutB = Payout::query()->where('provider_id', $businessB->id)->sole();

    expect($payoutA->amount->toDecimal())->toBe('150.00');
    expect($payoutB->amount->toDecimal())->toBe('75.00');
    expect(Payment::query()->whereNull('payout_id')->where('payable_type', 'booking')->count())->toBe(0);
});

it('never double-counts a payment across two nightly runs', function () {
    [, $business] = bookingProvider();
    succeededBookingPayment($business, '100.00');

    (new RunProviderPayoutJob())->handle();
    (new RunProviderPayoutJob())->handle();

    expect(Payout::query()->where('provider_id', $business->id)->count())->toBe(1);
    expect(Payout::query()->where('provider_id', $business->id)->sole()->amount->toDecimal())->toBe('100.00');
});

it('exposes the provider earnings ledger via GET /provider/me/earnings', function () {
    [$providerUser, $business] = bookingProvider();
    succeededBookingPayment($business, '200.00');

    (new RunProviderPayoutJob())->handle();

    $this->withHeaders(authHeader($providerUser))
        ->getJson('/api/v1/provider/me/earnings')
        ->assertOk()
        ->assertJsonPath('data.0.amount', '200.00')
        ->assertJsonPath('data.0.currency', 'CAD');
});
