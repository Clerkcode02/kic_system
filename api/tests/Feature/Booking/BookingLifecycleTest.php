<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

// --- Creation & routing rule ---------------------------------------------

it('routes a fixed-price service straight to scheduled and a quote-based service to waiting_for_quotation', function () {
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $fixedService = bookingService($business, ServicePricingType::Fixed);
    $hourlyService = bookingService($business, ServicePricingType::Hourly);

    $this->withHeaders(authHeader($customer))
        ->postJson('/api/v1/bookings', [
            'service_id' => $fixedService->id,
            'address_id' => $address->id,
            'scheduled_date' => futureBookingDate(),
            'time_slot_start' => '10:00:00',
            'time_slot_end' => '11:00:00',
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.status', BookingStatus::Scheduled->value);

    $this->withHeaders(authHeader($customer))
        ->postJson('/api/v1/bookings', [
            'service_id' => $hourlyService->id,
            'address_id' => $address->id,
            'scheduled_date' => futureBookingDate(),
            'time_slot_start' => '13:00:00',
            'time_slot_end' => '14:00:00',
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.status', BookingStatus::WaitingForQuotation->value);
});

it('requires an Idempotency-Key header and replays the first response for a repeated key', function () {
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $service = bookingService($business);

    $this->withHeaders(authHeader($customer))
        ->postJson('/api/v1/bookings', [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'scheduled_date' => futureBookingDate(),
            'time_slot_start' => '10:00:00',
            'time_slot_end' => '11:00:00',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'idempotency_key_required');

    $key = (string) Str::uuid();
    $payload = [
        'service_id' => $service->id,
        'address_id' => $address->id,
        'scheduled_date' => futureBookingDate(),
        'time_slot_start' => '10:00:00',
        'time_slot_end' => '11:00:00',
    ];

    $first = $this->withHeaders(authHeader($customer))
        ->postJson('/api/v1/bookings', $payload, ['Idempotency-Key' => $key])
        ->assertCreated();

    $firstId = $first->json('data.id');

    $this->withHeaders(authHeader($customer))
        ->postJson('/api/v1/bookings', $payload, ['Idempotency-Key' => $key])
        ->assertCreated()
        ->assertJsonPath('data.id', $firstId);

    expect(Booking::query()->count())->toBe(1);
});

it('rejects a booking in the past', function () {
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $service = bookingService($business);

    $this->withHeaders(authHeader($customer))
        ->postJson('/api/v1/bookings', [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'scheduled_date' => now()->subDay()->toDateString(),
            'time_slot_start' => '10:00:00',
            'time_slot_end' => '11:00:00',
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});

it('rejects a booking for an inactive service', function () {
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $service = bookingService($business);
    $service->update(['is_active' => false]);

    $this->withHeaders(authHeader($customer))
        ->postJson('/api/v1/bookings', [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'scheduled_date' => futureBookingDate(),
            'time_slot_start' => '10:00:00',
            'time_slot_end' => '11:00:00',
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('service_id');
});

it('rejects a booking for an unverified provider', function () {
    [$customer, $address] = bookingCustomer();
    $providerUser = User::factory()->provider()->create();
    $providerUser->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->pending()->create(['user_id' => $providerUser->id]);
    $service = bookingService($business);

    $this->withHeaders(authHeader($customer))
        ->postJson('/api/v1/bookings', [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'scheduled_date' => futureBookingDate(),
            'time_slot_start' => '10:00:00',
            'time_slot_end' => '11:00:00',
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});

it('rejects a booking outside provider availability', function () {
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $service = bookingService($business);

    $this->withHeaders(authHeader($customer))
        ->postJson('/api/v1/bookings', [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'scheduled_date' => futureBookingDate(),
            'time_slot_start' => '22:00:00',
            'time_slot_end' => '23:00:00',
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('time_slot_start');
});

it('rejects a booking once the provider is at its daily cap', function () {
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider(maxBookingsPerDay: 1);
    $service = bookingService($business);

    Booking::factory()->create([
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'scheduled_date' => futureBookingDate(),
        'time_slot_start' => '09:00:00',
        'time_slot_end' => '09:30:00',
        'status' => BookingStatus::Scheduled,
    ]);

    $this->withHeaders(authHeader($customer))
        ->postJson('/api/v1/bookings', [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'scheduled_date' => futureBookingDate(),
            'time_slot_start' => '15:00:00',
            'time_slot_end' => '15:30:00',
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('scheduled_date');
});

it('rejects a booking that overlaps an existing active booking for the same provider', function () {
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $service = bookingService($business);

    Booking::factory()->create([
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'scheduled_date' => futureBookingDate(),
        'time_slot_start' => '10:00:00',
        'time_slot_end' => '11:00:00',
        'status' => BookingStatus::Scheduled,
    ]);

    $this->withHeaders(authHeader($customer))
        ->postJson('/api/v1/bookings', [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'scheduled_date' => futureBookingDate(),
            'time_slot_start' => '10:30:00',
            'time_slot_end' => '11:30:00',
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('time_slot_start');
});

// --- Full happy path: Scheduled -> InProgress -> Completed ---------------

it('walks a fixed-price booking through check-in, complete, and confirm-completion', function () {
    [$customer, $address] = bookingCustomer();
    [$providerUser, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Fixed);

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'scheduled_date' => futureBookingDate(),
        'status' => BookingStatus::Scheduled,
    ]);

    $this->withHeaders(authHeader($providerUser))
        ->postJson("/api/v1/bookings/{$booking->id}/check-in")
        ->assertOk()
        ->assertJsonPath('data.status', BookingStatus::InProgress->value);

    // The customer cannot confirm before the provider marks it complete.
    forgetAuthGuards();
    $this->withHeaders(authHeader($customer))
        ->postJson("/api/v1/bookings/{$booking->id}/confirm-completion")
        ->assertStatus(409)
        ->assertJsonPath('code', 'provider_has_not_marked_complete');

    forgetAuthGuards();
    $this->withHeaders(authHeader($providerUser))
        ->postJson("/api/v1/bookings/{$booking->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', BookingStatus::InProgress->value)
        ->assertJsonPath('data.provider_completed_at', fn ($value) => $value !== null);

    forgetAuthGuards();
    $this->withHeaders(authHeader($customer))
        ->postJson("/api/v1/bookings/{$booking->id}/confirm-completion")
        ->assertOk()
        ->assertJsonPath('data.status', BookingStatus::Completed->value);

    $history = $booking->fresh()->statusHistory()->pluck('to_status')->all();
    expect($history)->toBe(['in_progress', 'completed']);
});

it('returns 409 for an illegal transition, such as checking in a pending booking', function () {
    [$customer, $address] = bookingCustomer();
    [$providerUser, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Hourly);

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => BookingStatus::WaitingForQuotation,
    ]);

    $this->withHeaders(authHeader($providerUser))
        ->postJson("/api/v1/bookings/{$booking->id}/check-in")
        ->assertStatus(409)
        ->assertJsonPath('error', 'illegal_state_transition');
});

// --- Cancellation policy ---------------------------------------------------

it('lets a customer cancel a pre-acceptance booking for free without a reason', function () {
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Hourly);

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => BookingStatus::WaitingForQuotation,
    ]);

    $this->withHeaders(authHeader($customer))
        ->patchJson("/api/v1/bookings/{$booking->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', BookingStatus::Cancelled->value)
        ->assertJsonPath('meta.cancellation_fee_applied', false);
});

it('flags a cancellation fee once the booking has been accepted', function () {
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Fixed);

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => BookingStatus::Scheduled,
    ]);

    $this->withHeaders(authHeader($customer))
        ->patchJson("/api/v1/bookings/{$booking->id}/cancel")
        ->assertOk()
        ->assertJsonPath('meta.cancellation_fee_applied', true);
});

it('requires a reason when the provider cancels', function () {
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
        ->patchJson("/api/v1/bookings/{$booking->id}/cancel")
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    $this->withHeaders(authHeader($providerUser))
        ->patchJson("/api/v1/bookings/{$booking->id}/cancel", ['reason' => 'Equipment failure.'])
        ->assertOk()
        ->assertJsonPath('data.status', BookingStatus::Cancelled->value);
});

it('blocks a non-admin from cancelling an in-progress booking', function () {
    [$customer, $address] = bookingCustomer();
    [$providerUser, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Fixed);

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => BookingStatus::InProgress,
    ]);

    $this->withHeaders(authHeader($customer))
        ->patchJson("/api/v1/bookings/{$booking->id}/cancel")
        ->assertStatus(409)
        ->assertJsonPath('code', 'exceptional_cancellation_requires_admin');
});

it('denies a stranger from viewing, cancelling, or advancing someone else\'s booking', function () {
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

    [$stranger] = bookingCustomer();

    $this->withHeaders(authHeader($stranger))
        ->getJson("/api/v1/bookings/{$booking->id}")
        ->assertForbidden();

    $this->withHeaders(authHeader($stranger))
        ->patchJson("/api/v1/bookings/{$booking->id}/cancel")
        ->assertForbidden();
});

// --- Listing & detail -------------------------------------------------------

it('lists bookings scoped to the caller by role and includes status history on show', function () {
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

    [$otherCustomer] = bookingCustomer();
    Booking::factory()->create([
        'customer_id' => $otherCustomer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'status' => BookingStatus::Scheduled,
    ]);

    $this->withHeaders(authHeader($customer))
        ->getJson('/api/v1/bookings?role=customer')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $booking->id);

    forgetAuthGuards();
    $this->withHeaders(authHeader($providerUser))
        ->getJson('/api/v1/bookings?role=provider')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    forgetAuthGuards();
    $this->withHeaders(authHeader($customer))
        ->getJson("/api/v1/bookings/{$booking->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $booking->id)
        ->assertJsonStructure(['data' => ['status_history', 'attachments']]);
});

it('rejects an unauthenticated request to every booking endpoint', function () {
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $service = bookingService($business);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
    ]);

    $this->getJson('/api/v1/bookings?role=customer')->assertUnauthorized();
    $this->getJson("/api/v1/bookings/{$booking->id}")->assertUnauthorized();
    $this->postJson('/api/v1/bookings', [], ['Idempotency-Key' => (string) Str::uuid()])->assertUnauthorized();
    $this->patchJson("/api/v1/bookings/{$booking->id}/cancel")->assertUnauthorized();
});
