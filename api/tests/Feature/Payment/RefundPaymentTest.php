<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function refundableCompletedBooking(string $amount = '100.00'): Payment
{
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Fixed);

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => BookingStatus::Completed,
    ]);

    return Payment::factory()->succeeded()->forBooking($booking)->create([
        'amount' => \App\Support\ValueObjects\Money::fromDecimal($amount, 'CAD'),
        'platform_fee_amount' => \App\Support\ValueObjects\Money::fromDecimal('0.00', 'CAD'),
        'provider_net_amount' => \App\Support\ValueObjects\Money::fromDecimal($amount, 'CAD'),
    ]);
}

function refundAdmin(): User
{
    $user = User::factory()->admin()->create();
    $user->assignRole(RoleName::Admin->value);

    return $user;
}

function refundSuperAdmin(): User
{
    $user = User::factory()->admin()->create();
    $user->assignRole(RoleName::SuperAdmin->value);

    return $user;
}

it('lets an admin refund a payment below the large-amount threshold', function () {
    $payment = refundableCompletedBooking('100.00');

    $this->withHeaders(authHeader(refundAdmin()))
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['reason' => 'Customer complaint.'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertOk()
        ->assertJsonPath('data.status', 'succeeded')
        ->assertJsonPath('data.amount', '100.00');

    expect($payment->fresh()->status)->toBe(\App\Domain\Payment\Enums\PaymentStatus::Refunded);
    $booking = $payment->payable;
    expect($booking->fresh()->status)->toBe(BookingStatus::Refunded);
    expect($booking->fresh()->payment_status)->toBe(\App\Domain\Booking\Enums\BookingPaymentStatus::Refunded);
});

it('blocks a regular admin from refunding above the configurable threshold', function () {
    $payment = refundableCompletedBooking('900.00');

    $this->withHeaders(authHeader(refundAdmin()))
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(403)
        ->assertJsonPath('code', 'refund_requires_elevated_admin');

    expect($payment->fresh()->status)->toBe(\App\Domain\Payment\Enums\PaymentStatus::Succeeded);
});

it('lets a super admin refund above the configurable threshold', function () {
    $payment = refundableCompletedBooking('900.00');

    $this->withHeaders(authHeader(refundSuperAdmin()))
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertOk()
        ->assertJsonPath('data.amount', '900.00');

    expect($payment->fresh()->status)->toBe(\App\Domain\Payment\Enums\PaymentStatus::Refunded);
});

it('rejects a partial refund amount larger than the original payment', function () {
    $payment = refundableCompletedBooking('50.00');

    $this->withHeaders(authHeader(refundAdmin()))
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['amount' => '75.00'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409)
        ->assertJsonPath('code', 'invalid_refund_amount');
});

it('denies a non-admin from issuing a refund', function () {
    $payment = refundableCompletedBooking();
    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);

    $this->withHeaders(authHeader($customer))
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", [])
        ->assertForbidden();
});
