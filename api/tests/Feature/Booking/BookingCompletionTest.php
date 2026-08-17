<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Models\Payment;
use App\Domain\Quotation\Models\Quotation;
use App\Support\ValueObjects\Money;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

/**
 * @return array{0: \App\Domain\User\Models\User, 1: \App\Domain\User\Models\User, 2: Booking}
 */
function inProgressBooking(): array
{
    [$customer, $address] = bookingCustomer();
    [$providerUser, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Fixed);

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'scheduled_date' => futureBookingDate(),
        'status' => BookingStatus::InProgress,
    ]);

    return [$customer, $providerUser, $booking];
}

it('denies a stranger customer from confirming completion of someone else\'s booking', function () {
    [, $providerUser, $booking] = inProgressBooking();
    $booking->update(['provider_completed_at' => now()]);

    [$stranger] = bookingCustomer();

    $this->withHeaders(authHeader($stranger))
        ->postJson("/api/v1/bookings/{$booking->id}/confirm-completion")
        ->assertForbidden();

    expect($booking->fresh()->status)->toBe(BookingStatus::InProgress);
});

it('rejects an unauthenticated confirm-completion request', function () {
    [, , $booking] = inProgressBooking();

    $this->postJson("/api/v1/bookings/{$booking->id}/confirm-completion")
        ->assertUnauthorized();
});

it('returns 409 when the provider has not yet marked the booking complete', function () {
    [$customer, , $booking] = inProgressBooking();

    $this->withHeaders(authHeader($customer))
        ->postJson("/api/v1/bookings/{$booking->id}/confirm-completion")
        ->assertStatus(409)
        ->assertJsonPath('code', 'provider_has_not_marked_complete');

    expect($booking->fresh()->status)->toBe(BookingStatus::InProgress);
});

it('returns 409 when confirming completion of a booking that is not in progress', function () {
    [$customer, , $booking] = inProgressBooking();
    $booking->update(['status' => BookingStatus::Scheduled, 'provider_completed_at' => now()]);

    $this->withHeaders(authHeader($customer))
        ->postJson("/api/v1/bookings/{$booking->id}/confirm-completion")
        ->assertStatus(409)
        ->assertJsonPath('error', 'illegal_state_transition');
});

it('captures the deposit remainder as a new pending payment on completion confirmation', function () {
    [$customer, , $booking] = inProgressBooking();
    $booking->update(['provider_completed_at' => now()]);

    $quotation = Quotation::factory()->accepted()->withDeposit(25.0)->create([
        'booking_id' => $booking->id,
        'total_amount' => Money::fromDecimal('200.00', 'CAD'),
    ]);

    Payment::factory()->forBooking($booking)->create([
        'type' => PaymentType::Deposit,
        'status' => PaymentStatus::Succeeded,
        'amount' => Money::fromDecimal('50.00', 'CAD'),
    ]);

    $this->withHeaders(authHeader($customer))
        ->postJson("/api/v1/bookings/{$booking->id}/confirm-completion")
        ->assertOk()
        ->assertJsonPath('data.status', BookingStatus::Completed->value);

    $remainderPayment = Payment::query()
        ->where('payable_type', 'booking')
        ->where('payable_id', $booking->id)
        ->where('type', PaymentType::Partial)
        ->first();

    expect($remainderPayment)->not->toBeNull()
        ->and($remainderPayment->status)->toBe(PaymentStatus::Pending)
        ->and($remainderPayment->amount->toDecimal())->toBe('150.00')
        ->and($remainderPayment->platform_fee_amount->isZero())->toBeTrue();

    expect($quotation->fresh())->not->toBeNull();
});

it('does not create a remainder payment when the deposit already covers the full total', function () {
    [$customer, , $booking] = inProgressBooking();
    $booking->update(['provider_completed_at' => now()]);

    Quotation::factory()->accepted()->withDeposit(100.0)->create([
        'booking_id' => $booking->id,
        'total_amount' => Money::fromDecimal('200.00', 'CAD'),
    ]);

    Payment::factory()->forBooking($booking)->create([
        'type' => PaymentType::Deposit,
        'status' => PaymentStatus::Succeeded,
        'amount' => Money::fromDecimal('200.00', 'CAD'),
    ]);

    $this->withHeaders(authHeader($customer))
        ->postJson("/api/v1/bookings/{$booking->id}/confirm-completion")
        ->assertOk()
        ->assertJsonPath('data.status', BookingStatus::Completed->value);

    $remainderPayment = Payment::query()
        ->where('payable_type', 'booking')
        ->where('payable_id', $booking->id)
        ->where('type', PaymentType::Partial)
        ->first();

    expect($remainderPayment)->toBeNull();
});

it('does not create a remainder payment for a non-deposit quotation', function () {
    [$customer, , $booking] = inProgressBooking();
    $booking->update(['provider_completed_at' => now()]);

    Quotation::factory()->accepted()->create([
        'booking_id' => $booking->id,
        'total_amount' => Money::fromDecimal('200.00', 'CAD'),
        'deposit_percentage' => null,
    ]);

    $this->withHeaders(authHeader($customer))
        ->postJson("/api/v1/bookings/{$booking->id}/confirm-completion")
        ->assertOk()
        ->assertJsonPath('data.status', BookingStatus::Completed->value);

    $remainderPayment = Payment::query()
        ->where('payable_type', 'booking')
        ->where('payable_id', $booking->id)
        ->where('type', PaymentType::Partial)
        ->first();

    expect($remainderPayment)->toBeNull();
});
