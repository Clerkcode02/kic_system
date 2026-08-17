<?php

declare(strict_types=1);

use App\Domain\Booking\Actions\TransitionBookingStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Payment\Models\Payment;
use App\Domain\Quotation\Jobs\ExpireStaleQuotationsJob;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\User\Enums\RoleName;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

/**
 * @return array{0: \App\Domain\User\Models\User, 1: \App\Domain\Business\Models\Business, 2: Booking}
 */
function quotableBooking(): array
{
    [$customer, $address] = bookingCustomer();
    [$providerUser, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Hourly);

    $booking = Booking::factory()->waitingForQuotation()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
    ]);

    return [$providerUser, $business, $booking];
}

// --- Server-side total computation -----------------------------------------

it('computes the total server-side and ignores a tampered client total', function () {
    [$providerUser, , $booking] = quotableBooking();

    $response = $this->withHeaders(authHeader($providerUser))
        ->postJson("/api/v1/bookings/{$booking->id}/quotations", [
            'labor_cost' => '100.00',
            'materials_cost' => '50.00',
            'additional_fees' => '10.00',
            // Tampered/forged fields a malicious client might send — the
            // FormRequest doesn't even define rules for these, and the
            // Action never reads them off the request payload.
            'total_amount' => '1.00',
            'platform_fee' => '0.00',
            'tax_amount' => '0.00',
        ])
        ->assertCreated();

    // subtotal 160.00 -> platform fee 10% = 16.00, tax 13% = 20.80, total 196.80
    $response
        ->assertJsonPath('data.platform_fee', '16.00')
        ->assertJsonPath('data.tax_amount', '20.80')
        ->assertJsonPath('data.total_amount', '196.80')
        ->assertJsonPath('data.revision_number', 1)
        ->assertJsonPath('data.status', 'sent');

    expect(Quotation::query()->first()->total_amount->toDecimal())->toBe('196.80');
});

it('returns 409 when quoting a booking that is not in WaitingForQuotation', function () {
    [$customer, $address] = bookingCustomer();
    [$providerUser, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Fixed);

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => BookingStatus::Scheduled,
    ]);

    $this->withHeaders(authHeader($providerUser))
        ->postJson("/api/v1/bookings/{$booking->id}/quotations", [
            'labor_cost' => '100.00',
            'materials_cost' => '0',
            'additional_fees' => '0',
        ])
        ->assertStatus(409)
        ->assertJsonPath('error', 'illegal_state_transition');
});

it('rejects a stranger provider from quoting someone else\'s booking', function () {
    [, , $booking] = quotableBooking();
    [$stranger] = bookingProvider();

    $this->withHeaders(authHeader($stranger))
        ->postJson("/api/v1/bookings/{$booking->id}/quotations", [
            'labor_cost' => '100.00',
            'materials_cost' => '0',
            'additional_fees' => '0',
        ])
        ->assertForbidden();
});

// --- send -> accept ----------------------------------------------------------

it('walks send -> accept, creating a pending payment and a payment intent', function () {
    [$providerUser, , $booking] = quotableBooking();

    $this->withHeaders(authHeader($providerUser))
        ->postJson("/api/v1/bookings/{$booking->id}/quotations", [
            'labor_cost' => '100.00',
            'materials_cost' => '0',
            'additional_fees' => '0',
        ])
        ->assertCreated();

    $quotation = Quotation::query()->firstOrFail();
    $customer = $booking->customer;

    forgetAuthGuards();
    $this->withHeaders(authHeader($customer))
        ->postJson("/api/v1/quotations/{$quotation->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonPath('meta.payment.status', 'pending')
        ->assertJsonPath('meta.payment.client_secret', fn ($value) => is_string($value));

    expect($booking->fresh()->status)->toBe(BookingStatus::Accepted);
    expect(Payment::query()->where('payable_id', $booking->id)->exists())->toBeTrue();
});

// --- send -> reject -> revise --------------------------------------------------

it('walks send -> reject -> revise, incrementing revision_number and superseding the old row', function () {
    [$providerUser, , $booking] = quotableBooking();

    $this->withHeaders(authHeader($providerUser))
        ->postJson("/api/v1/bookings/{$booking->id}/quotations", [
            'labor_cost' => '100.00',
            'materials_cost' => '0',
            'additional_fees' => '0',
        ])
        ->assertCreated();

    $original = Quotation::query()->firstOrFail();
    $customer = $booking->customer;

    forgetAuthGuards();
    $this->withHeaders(authHeader($customer))
        ->postJson("/api/v1/quotations/{$original->id}/reject", ['reason' => 'Too expensive.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($booking->fresh()->status)->toBe(BookingStatus::WaitingForQuotation);

    forgetAuthGuards();
    $revised = $this->withHeaders(authHeader($providerUser))
        ->postJson("/api/v1/quotations/{$original->id}/revise", [
            'labor_cost' => '80.00',
            'materials_cost' => '0',
            'additional_fees' => '0',
        ])
        ->assertCreated()
        ->assertJsonPath('data.revision_number', 2)
        ->assertJsonPath('data.previous_quotation_id', $original->id)
        ->assertJsonPath('data.status', 'sent');

    expect($original->fresh()->status)->toBe(\App\Domain\Quotation\Enums\QuotationStatus::Superseded);
    expect(Quotation::query()->count())->toBe(2);
    expect($booking->fresh()->status)->toBe(BookingStatus::WaitingForCustomer);

    $revisedId = $revised->json('data.id');
    expect($revisedId)->not->toBe($original->id);
});

it('blocks sending a quotation when the provider cannot receive funds yet', function () {
    [$customer, $address] = bookingCustomer();
    $providerUser = \App\Domain\User\Models\User::factory()->provider()->create();
    $providerUser->assignRole(RoleName::ProviderOwner->value);
    // Verified but Stripe Connect onboarding incomplete — no payoutsEnabled().
    $business = Business::factory()->verified()->create(['user_id' => $providerUser->id]);
    $service = bookingService($business, ServicePricingType::Hourly);

    $booking = Booking::factory()->waitingForQuotation()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
    ]);

    $this->withHeaders(authHeader($providerUser))
        ->postJson("/api/v1/bookings/{$booking->id}/quotations", [
            'labor_cost' => '100.00',
            'materials_cost' => '0',
            'additional_fees' => '0',
        ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'provider_payouts_not_enabled');

    expect(Quotation::query()->count())->toBe(0);
});

it('blocks revising a quotation when the provider cannot receive funds yet', function () {
    [$providerUser, $business, $booking] = quotableBooking();

    $original = Quotation::factory()->sent()->create(['booking_id' => $booking->id]);
    $booking->update(['status' => BookingStatus::WaitingForCustomer]);

    // Payouts disabled after the quotation was sent (e.g. Stripe requirements
    // regressed) — revising must be blocked the same way sending is.
    $business->update(['stripe_payouts_enabled' => false]);

    $this->withHeaders(authHeader($providerUser))
        ->postJson("/api/v1/quotations/{$original->id}/revise", [
            'labor_cost' => '80.00',
            'materials_cost' => '0',
            'additional_fees' => '0',
        ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'provider_payouts_not_enabled');

    expect($original->fresh()->status)->toBe(\App\Domain\Quotation\Enums\QuotationStatus::Sent);
});

it('returns 409 when revising a quotation that is not in a revisable state', function () {
    [$providerUser, , $booking] = quotableBooking();

    $accepted = Quotation::factory()->accepted()->create(['booking_id' => $booking->id]);

    $this->withHeaders(authHeader($providerUser))
        ->postJson("/api/v1/quotations/{$accepted->id}/revise", [
            'labor_cost' => '80.00',
            'materials_cost' => '0',
            'additional_fees' => '0',
        ])
        ->assertStatus(409)
        ->assertJsonPath('error', 'illegal_state_transition');
});

it('revises a still-sent quotation (customer has not yet decided), hopping the booking back through WaitingForQuotation', function () {
    [$providerUser, , $booking] = quotableBooking();

    $original = Quotation::factory()->sent()->create(['booking_id' => $booking->id]);
    $booking->update(['status' => BookingStatus::WaitingForCustomer]);

    $this->withHeaders(authHeader($providerUser))
        ->postJson("/api/v1/quotations/{$original->id}/revise", [
            'labor_cost' => '80.00',
            'materials_cost' => '0',
            'additional_fees' => '0',
        ])
        ->assertCreated()
        ->assertJsonPath('data.revision_number', $original->revision_number + 1)
        ->assertJsonPath('data.status', 'sent');

    expect($original->fresh()->status)->toBe(\App\Domain\Quotation\Enums\QuotationStatus::Superseded);
    expect($booking->fresh()->status)->toBe(BookingStatus::WaitingForCustomer);
});

it('rejects an unauthenticated request to every quotation endpoint', function () {
    [, , $booking] = quotableBooking();
    $quotation = \App\Domain\Quotation\Models\Quotation::factory()->create(['booking_id' => $booking->id]);

    $this->postJson("/api/v1/bookings/{$booking->id}/quotations", [])->assertUnauthorized();
    $this->getJson("/api/v1/quotations/{$quotation->id}")->assertUnauthorized();
    $this->postJson("/api/v1/quotations/{$quotation->id}/accept")->assertUnauthorized();
    $this->postJson("/api/v1/quotations/{$quotation->id}/reject")->assertUnauthorized();
    $this->postJson("/api/v1/quotations/{$quotation->id}/revise")->assertUnauthorized();
});

// --- Expiry sweep --------------------------------------------------------------

it('expires only eligible stale quotations and routes their booking to quotation_expired', function () {
    [, , $expiredBooking] = quotableBooking();
    $expiredBooking->update(['status' => BookingStatus::WaitingForCustomer]);
    $expiredQuotation = Quotation::factory()->sent()->create([
        'booking_id' => $expiredBooking->id,
        'valid_until' => now()->subHour(),
    ]);

    [, , $freshBooking] = quotableBooking();
    $freshBooking->update(['status' => BookingStatus::WaitingForCustomer]);
    $freshQuotation = Quotation::factory()->sent()->create([
        'booking_id' => $freshBooking->id,
        'valid_until' => now()->addDay(),
    ]);

    (new ExpireStaleQuotationsJob())->handle(app(TransitionBookingStatus::class));

    expect($expiredQuotation->fresh()->status)->toBe(\App\Domain\Quotation\Enums\QuotationStatus::Expired);
    expect($expiredBooking->fresh()->status)->toBe(BookingStatus::QuotationExpired);

    expect($freshQuotation->fresh()->status)->toBe(\App\Domain\Quotation\Enums\QuotationStatus::Sent);
    expect($freshBooking->fresh()->status)->toBe(BookingStatus::WaitingForCustomer);
});
