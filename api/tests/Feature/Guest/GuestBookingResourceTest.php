<?php

declare(strict_types=1);

use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Services\BookingAccessTokenService;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\Quotation\Models\QuotationLineItem;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Notification::fake();
});

/**
 * SRS §6.1: the guest read model is an explicit allow-list, not the
 * registered resource minus a few fields. These tests pin the exact key set
 * so that adding a field to GuestBookingResource is a deliberate, reviewed
 * act rather than something that happens by accident when BookingResource
 * grows.
 */
it('exposes exactly the documented key set and nothing more', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business, ServicePricingType::Hourly);

    $booking = Booking::factory()->guest()->waitingForCustomer()->create([
        'provider_id' => $business->id,
        'service_id' => $service->id,
    ]);

    $quotation = Quotation::factory()->create(['booking_id' => $booking->id, 'status' => 'sent']);
    QuotationLineItem::factory()->create(['quotation_id' => $quotation->id]);

    $token = app(BookingAccessTokenService::class)->issue($booking);

    $data = $this->withHeaders(['X-Booking-Token' => $token['plaintext']])
        ->getJson("/api/v1/guest/bookings/{$booking->booking_number}")
        ->assertOk()
        ->json('data');

    expect(array_keys($data))->toEqualCanonicalizing([
        'booking_number',
        'status',
        'payment_status',
        'scheduled_date',
        'time_slot_start',
        'time_slot_end',
        'notes',
        'service',
        'provider',
        'service_address',
        'quotation',
        'timeline',
        'created_at',
    ]);

    expect(array_keys($data['service']))->toEqualCanonicalizing([
        'title', 'pricing_type', 'base_price', 'currency',
    ]);

    expect(array_keys($data['provider']))->toEqualCanonicalizing([
        'display_name', 'rating_avg',
    ]);

    expect(array_keys($data['service_address']))->toEqualCanonicalizing([
        'line1', 'line2', 'city', 'province', 'postal_code',
    ]);

    expect(array_keys($data['quotation']))->toEqualCanonicalizing([
        'id', 'labor_cost', 'materials_cost', 'additional_fees', 'platform_fee',
        'tax_amount', 'discount_amount', 'total_amount', 'deposit_percentage',
        'currency', 'valid_until', 'revision_number', 'status', 'line_items',
    ]);

    expect(array_keys($data['timeline'][0] ?? ['from_status' => null, 'to_status' => null, 'note' => null, 'occurred_at' => null]))
        ->toEqualCanonicalizing(['from_status', 'to_status', 'note', 'occurred_at']);
});

it('leaks no internal ids, provider PII, or owning-customer details', function () {
    [$providerUser, $business] = guestTestProvider();
    $service = guestTestService($business);

    $booking = Booking::factory()->guest('dana@example.com')->create([
        'provider_id' => $business->id,
        'service_id' => $service->id,
    ]);

    $token = app(BookingAccessTokenService::class)->issue($booking);

    $body = $this->withHeaders(['X-Booking-Token' => $token['plaintext']])
        ->getJson("/api/v1/guest/bookings/{$booking->booking_number}")
        ->assertOk()
        ->getContent();

    // Internal identifiers.
    expect($body)->not->toContain($booking->id);
    expect($body)->not->toContain($business->id);
    expect($body)->not->toContain($service->id);

    // Provider PII.
    expect($body)->not->toContain($providerUser->email);
    expect($body)->not->toContain($providerUser->name);

    // The guest's own contact details are not echoed back either — the
    // holder already knows them, and echoing them turns a stolen token into
    // a PII disclosure on top of a booking-access one.
    expect($body)->not->toContain('dana@example.com');
    expect($body)->not->toContain((string) $booking->guest_phone);

    // Nothing token- or audit-shaped.
    expect($body)->not->toContain('token_hash');
    expect($body)->not->toContain('access_token');
    expect($body)->not->toContain('changed_by');
});

it('offers only the live quotation, not superseded revisions', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business, ServicePricingType::Hourly);

    $booking = Booking::factory()->guest()->waitingForCustomer()->create([
        'provider_id' => $business->id,
        'service_id' => $service->id,
    ]);

    Quotation::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'superseded',
        'revision_number' => 1,
    ]);
    $live = Quotation::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'sent',
        'revision_number' => 2,
    ]);

    $token = app(BookingAccessTokenService::class)->issue($booking);

    $this->withHeaders(['X-Booking-Token' => $token['plaintext']])
        ->getJson("/api/v1/guest/bookings/{$booking->booking_number}")
        ->assertOk()
        ->assertJsonPath('data.quotation.id', $live->id)
        ->assertJsonPath('data.quotation.revision_number', 2);
});
