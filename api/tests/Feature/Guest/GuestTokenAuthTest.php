<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAccessToken;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Payment\Models\Payment;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Notification::fake();
});

/**
 * SRS §6.1 / CLAUDE.md §9: the guest surface must be reachable with **no
 * cookies at all**, because a mobile client (Phase 2) has no cookie jar and
 * because no cookie/CSRF assumption may reach domain code.
 *
 * Every request below asserts that after it completes, the test client is
 * still holding zero cookies — if any handler started a session or set a
 * CSRF cookie, this fails.
 */
function assertCookieless(TestResponse $response): TestResponse
{
    $cookies = array_map(
        fn ($cookie) => $cookie->getName(),
        $response->baseResponse->headers->getCookies(),
    );

    expect($cookies)->toBe([], 'A guest response set a cookie; the guest surface must be cookieless.');

    return $response;
}

it('runs the entire guest lifecycle with no cookies at all', function () {
    [, $business] = guestTestProvider();
    $providerUser = $business->user;
    $providerUser->assignRole(RoleName::ProviderOwner->value);
    $service = guestTestService($business, ServicePricingType::Hourly);

    // 1. Create — anonymous, no session, no CSRF token.
    $created = $this->withHeaders(guestIdempotencyHeader())
        ->postJson('/api/v1/bookings', guestBookingPayload($service))
        ->assertCreated();

    assertCookieless($created);

    $bookingNumber = $created->json('data.booking_number');
    $token = $created->json('meta.access_token');
    $tokenHeader = ['X-Booking-Token' => $token];

    $booking = Booking::query()->where('booking_number', $bookingNumber)->firstOrFail();
    expect($booking->status)->toBe(BookingStatus::WaitingForQuotation);

    // 2. Track.
    assertCookieless(
        $this->withHeaders($tokenHeader)
            ->getJson("/api/v1/guest/bookings/{$bookingNumber}")
            ->assertOk()
            ->assertJsonPath('data.booking_number', $bookingNumber)
            ->assertJsonPath('data.status', BookingStatus::WaitingForQuotation->value)
    );

    // 3. Provider quotes it (authenticated — the other side of the flow is
    //    unchanged by guest booking).
    $this->actingAs($providerUser, 'sanctum')
        ->postJson("/api/v1/bookings/{$booking->id}/quotations", [
            'labor_cost' => '200.00',
            'materials_cost' => '50.00',
            'additional_fees' => '0.00',
            'discount_amount' => '0.00',
            'line_items' => [
                ['description' => 'Labour', 'quantity' => 2, 'unit_price' => '100.00'],
            ],
        ])->assertCreated();

    $quotation = Quotation::query()->where('booking_id', $booking->id)->firstOrFail();

    // actingAs() persists for the rest of the test, so the guest steps
    // below would otherwise run as the provider. Drop back to anonymous —
    // the whole point of this test is that the token alone is enough.
    $this->app['auth']->forgetGuards();

    // 4. Accept the quotation with the token — same Action the registered
    //    path uses, including the destination-charge intent.
    $accepted = assertCookieless(
        $this->withHeaders([...$tokenHeader, ...guestIdempotencyHeader()])
            ->postJson("/api/v1/guest/quotations/{$quotation->id}/accept")
            ->assertOk()
    );

    expect($accepted->json('meta.payment.client_secret'))->not->toBeNull();
    expect($booking->fresh()->status)->toBe(BookingStatus::Accepted);
    expect(Payment::query()->where('payable_id', $booking->id)->count())->toBe(1);

    // 5. Track again and see the quotation reflected.
    assertCookieless(
        $this->withHeaders($tokenHeader)
            ->getJson("/api/v1/guest/bookings/{$bookingNumber}")
            ->assertOk()
            ->assertJsonPath('data.quotation.status', 'accepted')
    );

    // 6. Cancel.
    assertCookieless(
        $this->withHeaders($tokenHeader)
            ->patchJson("/api/v1/guest/bookings/{$bookingNumber}/cancel", ['reason' => 'Plans changed'])
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Cancelled->value)
            ->assertJsonPath('meta.cancellation_fee_applied', true)
    );
});

it('creates a payment intent for a tracked guest booking', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business, ServicePricingType::Hourly);

    $booking = Booking::factory()->guest()->accepted()->create([
        'provider_id' => $business->id,
        'service_id' => $service->id,
    ]);

    Quotation::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'accepted',
    ]);

    $token = app(\App\Domain\Booking\Services\BookingAccessTokenService::class)->issue($booking);

    $this->withHeaders(['X-Booking-Token' => $token['plaintext'], ...guestIdempotencyHeader()])
        ->postJson('/api/v1/guest/payments/intents')
        ->assertCreated()
        ->assertJsonPath('meta.client_secret', fn ($secret) => $secret !== null);
});

// --- Token scoping and failure modes --------------------------------------

it('returns 404 when booking A\'s token is presented against booking B', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    $bookingA = Booking::factory()->guest()->create(['provider_id' => $business->id, 'service_id' => $service->id]);
    $bookingB = Booking::factory()->guest()->create(['provider_id' => $business->id, 'service_id' => $service->id]);

    $tokenA = app(\App\Domain\Booking\Services\BookingAccessTokenService::class)->issue($bookingA);

    $this->withHeaders(['X-Booking-Token' => $tokenA['plaintext']])
        ->getJson("/api/v1/guest/bookings/{$bookingB->booking_number}")
        ->assertNotFound();

    // And the same token still works for its own booking, proving the 404
    // was scoping and not a broken token.
    $this->withHeaders(['X-Booking-Token' => $tokenA['plaintext']])
        ->getJson("/api/v1/guest/bookings/{$bookingA->booking_number}")
        ->assertOk();
});

it('returns 404 for an expired token', function () {
    [, $business] = guestTestProvider();
    $booking = Booking::factory()->guest()->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
    ]);

    $issued = app(\App\Domain\Booking\Services\BookingAccessTokenService::class)->issue($booking);

    BookingAccessToken::query()
        ->where('booking_id', $booking->id)
        ->update(['expires_at' => Date::now()->subMinute()]);

    $this->withHeaders(['X-Booking-Token' => $issued['plaintext']])
        ->getJson("/api/v1/guest/bookings/{$booking->booking_number}")
        ->assertNotFound();
});

it('returns 404 for a revoked token', function () {
    [, $business] = guestTestProvider();
    $booking = Booking::factory()->guest()->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
    ]);

    $service = app(\App\Domain\Booking\Services\BookingAccessTokenService::class);
    $issued = $service->issue($booking);
    $service->revokeAllFor($booking);

    $this->withHeaders(['X-Booking-Token' => $issued['plaintext']])
        ->getJson("/api/v1/guest/bookings/{$booking->booking_number}")
        ->assertNotFound();
});

it('returns 404 — never 403 — for a missing, unknown or malformed token', function () {
    [, $business] = guestTestProvider();
    $booking = Booking::factory()->guest()->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
    ]);

    $url = "/api/v1/guest/bookings/{$booking->booking_number}";

    // The booking demonstrably exists, and every one of these still 404s —
    // the API never confirms existence to a caller who can't already read
    // it (SRS §6.1).
    $this->getJson($url)->assertNotFound();
    $this->withHeaders(['X-Booking-Token' => ''])->getJson($url)->assertNotFound();
    $this->withHeaders(['X-Booking-Token' => str_repeat('a', 64)])->getJson($url)->assertNotFound();
    $this->withHeaders(['X-Booking-Token' => 'not-a-token'])->getJson($url)->assertNotFound();
});

it('never accepts a booking number plus email as read authorization', function () {
    [, $business] = guestTestProvider();
    $booking = Booking::factory()->guest('dana@example.com')->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
    ]);

    // Knowing both the booking number and the exact email is still not a
    // credential — only a token is.
    $this->getJson("/api/v1/guest/bookings/{$booking->booking_number}?email=dana@example.com")
        ->assertNotFound();

    $this->withHeaders(['X-Guest-Email' => 'dana@example.com'])
        ->getJson("/api/v1/guest/bookings/{$booking->booking_number}")
        ->assertNotFound();
});

it('returns 404 when a guest token is used against another guest\'s quotation', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business, ServicePricingType::Hourly);

    $mine = Booking::factory()->guest()->waitingForCustomer()->create(['provider_id' => $business->id, 'service_id' => $service->id]);
    $theirs = Booking::factory()->guest()->waitingForCustomer()->create(['provider_id' => $business->id, 'service_id' => $service->id]);

    $theirQuotation = Quotation::factory()->create(['booking_id' => $theirs->id, 'status' => 'sent']);

    $token = app(\App\Domain\Booking\Services\BookingAccessTokenService::class)->issue($mine);

    $this->withHeaders(['X-Booking-Token' => $token['plaintext'], ...guestIdempotencyHeader()])
        ->postJson("/api/v1/guest/quotations/{$theirQuotation->id}/accept")
        ->assertNotFound();
});

it('returns 404 rather than a 500 for a non-uuid quotation id', function () {
    [, $business] = guestTestProvider();
    $booking = Booking::factory()->guest()->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
    ]);

    $token = app(\App\Domain\Booking\Services\BookingAccessTokenService::class)->issue($booking);

    $this->withHeaders(['X-Booking-Token' => $token['plaintext'], ...guestIdempotencyHeader()])
        ->postJson('/api/v1/guest/quotations/not-a-uuid/accept')
        ->assertNotFound();
});

it('blocks a registered user from reading another actor\'s booking on the guest route', function () {
    [, $business] = guestTestProvider();
    $booking = Booking::factory()->guest()->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
    ]);

    $stranger = User::factory()->customer()->create();
    $stranger->assignRole(RoleName::Customer->value);

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/guest/bookings/{$booking->booking_number}")
        ->assertNotFound();
});

it('records a hashed guest actor label on the audit trail, never a raw email', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    $created = $this->postJson('/api/v1/bookings', guestBookingPayload($service), guestIdempotencyHeader())
        ->assertCreated();

    $booking = Booking::query()->where('booking_number', $created->json('data.booking_number'))->firstOrFail();

    // Scoped to this booking: ConcurrentGuestBookingTest commits rows
    // outside RefreshDatabase, so `booking.created` is not unique globally.
    $entry = \App\Domain\Audit\Models\AuditLog::query()
        ->where('action', 'booking.created')
        ->where('auditable_id', $booking->id)
        ->firstOrFail();

    expect($entry->actor_id)->toBeNull();
    expect($entry->actor_label)->toBe('guest:'.hash('sha256', 'dana@example.com'));
    expect($entry->actor_label)->not->toContain('dana@example.com');

    // Nothing anywhere in the trail holds the plaintext email.
    $all = \App\Domain\Audit\Models\AuditLog::query()->get()->toJson();
    expect($all)->not->toContain('dana@example.com');
});
