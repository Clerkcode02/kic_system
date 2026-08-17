<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAccessToken;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\ProviderAvailability;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Catalog\Models\Service;
use App\Domain\Platform\Services\SettingsRepository;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\Address;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Notification::fake();
});

/**
 * @return array{0: User, 1: Business}
 */
function guestTestProvider(int $maxBookingsPerDay = 10): array
{
    $user = User::factory()->provider()->create();
    $user->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->verified()->payoutsEnabled()->create([
        'user_id' => $user->id,
        'max_bookings_per_day' => $maxBookingsPerDay,
    ]);

    foreach (range(0, 6) as $day) {
        ProviderAvailability::factory()->create([
            'business_id' => $business->id,
            'day_of_week' => $day,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
        ]);
    }

    return [$user, $business];
}

function guestTestService(Business $business, ServicePricingType $pricingType = ServicePricingType::Fixed): Service
{
    return Service::factory()->create([
        'business_id' => $business->id,
        'pricing_type' => $pricingType,
        'is_active' => true,
    ]);
}

/**
 * @return array<string, mixed>
 */
function guestBookingPayload(Service $service, array $overrides = []): array
{
    return array_replace_recursive([
        'service_id' => $service->id,
        'scheduled_date' => now()->addDays(5)->toDateString(),
        'time_slot_start' => '09:00:00',
        'time_slot_end' => '11:00:00',
        'guest_name' => 'Dana Okafor',
        'guest_email' => 'dana@example.com',
        'guest_phone' => '+14165550143',
        'service_address' => [
            'line1' => '55 Front St W',
            'city' => 'Toronto',
            'province' => 'ON',
            'postal_code' => 'M5J 1E6',
            'lat' => 43.6426,
            'lng' => -79.3871,
        ],
    ], $overrides);
}

function guestIdempotencyHeader(): array
{
    return ['Idempotency-Key' => (string) Str::uuid()];
}

// --- Creation -------------------------------------------------------------

it('lets an anonymous visitor create a booking and returns the access token exactly once', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    $response = $this->postJson('/api/v1/bookings', guestBookingPayload($service), guestIdempotencyHeader())
        ->assertCreated();

    $bookingNumber = $response->json('data.booking_number');
    $plaintext = $response->json('meta.access_token');

    expect($bookingNumber)->toBeString()->not->toBeEmpty();
    expect($plaintext)->toBeString()->toHaveLength(64);

    $booking = Booking::query()->where('booking_number', $bookingNumber)->firstOrFail();

    expect($booking->customer_id)->toBeNull();
    expect($booking->address_id)->toBeNull();
    expect($booking->isGuest())->toBeTrue();
    expect($booking->guest_email_normalized)->toBe('dana@example.com');
    expect($booking->service_address_city)->toBe('Toronto');

    // Only the hash is persisted — the plaintext exists in the response and
    // the email, and nowhere else (CLAUDE.md §2).
    $token = BookingAccessToken::query()->where('booking_id', $booking->id)->firstOrFail();
    expect($token->token_hash)->toBe(hash('sha256', $plaintext));
    expect(BookingAccessToken::query()->where('token_hash', $plaintext)->exists())->toBeFalse();

    // The service-address snapshot is a real PostGIS point, so radius
    // search works without an addresses row (SRS §18).
    $hasLocation = DB::selectOne(
        'SELECT service_location IS NOT NULL AS present FROM bookings WHERE id = ?',
        [$booking->id],
    );
    expect($hasLocation->present)->toBeTrue();
});

it('routes a guest booking through the same state machine as a registered one', function () {
    [, $business] = guestTestProvider();

    $fixed = guestTestService($business, ServicePricingType::Fixed);
    $quoted = guestTestService($business, ServicePricingType::Hourly);

    $this->postJson('/api/v1/bookings', guestBookingPayload($fixed), guestIdempotencyHeader())
        ->assertCreated()
        ->assertJsonPath('data.status', BookingStatus::Scheduled->value);

    $this->postJson('/api/v1/bookings', guestBookingPayload($quoted, [
        'time_slot_start' => '13:00:00',
        'time_slot_end' => '15:00:00',
    ]), guestIdempotencyHeader())
        ->assertCreated()
        ->assertJsonPath('data.status', BookingStatus::WaitingForQuotation->value);
});

// --- The one-actor rule ---------------------------------------------------

it('rejects at the database level a booking with both actors or neither', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    $base = [
        'id' => (string) Str::uuid7(),
        'booking_number' => 'BK-'.Str::random(8),
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'scheduled_date' => now()->addDay()->toDateString(),
        'time_slot_start' => '09:00:00',
        'time_slot_end' => '10:00:00',
        'lat' => 43.6,
        'lng' => -79.4,
        'status' => BookingStatus::Pending->value,
        'payment_status' => 'unpaid',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $customer = User::factory()->customer()->create();

    // Both actors at once.
    expect(fn () => DB::table('bookings')->insert([
        ...$base,
        'customer_id' => $customer->id,
        'guest_name' => 'Both',
        'guest_email' => 'both@example.com',
        'guest_phone' => '+14165550000',
        'guest_email_normalized' => 'both@example.com',
    ]))->toThrow(QueryException::class);

    // Neither actor.
    expect(fn () => DB::table('bookings')->insert([
        ...$base,
        'id' => (string) Str::uuid7(),
        'booking_number' => 'BK-'.Str::random(8),
        'customer_id' => null,
    ]))->toThrow(QueryException::class);

    // A partial guest triple is just as invalid as none at all.
    expect(fn () => DB::table('bookings')->insert([
        ...$base,
        'id' => (string) Str::uuid7(),
        'booking_number' => 'BK-'.Str::random(8),
        'customer_id' => null,
        'guest_name' => 'Partial',
        'guest_email' => 'partial@example.com',
    ]))->toThrow(QueryException::class);
});

it('returns 422 when an authenticated caller sends guest contact fields', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);
    $address = Address::factory()->for($customer, 'user')->create();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/bookings', [
            ...guestBookingPayload($service),
            'address_id' => $address->id,
        ], guestIdempotencyHeader())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['guest_name', 'guest_email', 'guest_phone']);
});

it('returns 422 when an anonymous caller tries to use a saved address', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    $customer = User::factory()->customer()->create();
    $address = Address::factory()->for($customer, 'user')->create();

    $this->postJson('/api/v1/bookings', guestBookingPayload($service, [
        'address_id' => $address->id,
    ]), guestIdempotencyHeader())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['address_id']);
});

it('rejects a non-Canadian postal code or province', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    $this->postJson('/api/v1/bookings', guestBookingPayload($service, [
        'service_address' => ['postal_code' => '90210', 'province' => 'CA'],
    ]), guestIdempotencyHeader())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['service_address.postal_code', 'service_address.province']);
});

// --- Idempotency ----------------------------------------------------------

it('replays the original booking when a guest reuses an Idempotency-Key', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    $headers = guestIdempotencyHeader();
    $payload = guestBookingPayload($service);

    $first = $this->postJson('/api/v1/bookings', $payload, $headers)->assertCreated();
    $second = $this->postJson('/api/v1/bookings', $payload, $headers)->assertCreated();

    expect($second->json('data.booking_number'))->toBe($first->json('data.booking_number'));
    expect(Booking::query()->where('provider_id', $business->id)->count())->toBe(1);
});

it('scopes idempotency keys per guest email so two guests cannot collide on one key', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    // The same key value from two different guests must produce two
    // different bookings, not one guest silently receiving the other's.
    $sharedKey = ['Idempotency-Key' => 'shared-key-value'];

    $first = $this->postJson('/api/v1/bookings', guestBookingPayload($service), $sharedKey)
        ->assertCreated();

    $second = $this->postJson('/api/v1/bookings', guestBookingPayload($service, [
        'guest_email' => 'other@example.com',
        'time_slot_start' => '13:00:00',
        'time_slot_end' => '15:00:00',
    ]), $sharedKey)->assertCreated();

    expect($second->json('data.booking_number'))->not->toBe($first->json('data.booking_number'));

    // Scoped to this test's provider: ConcurrentGuestBookingTest commits
    // its rows outside RefreshDatabase, so a global count is not this
    // test's to assert on (same convention as ConcurrentBookingTest).
    expect(Booking::query()->where('provider_id', $business->id)->count())->toBe(2);
});

it('requires an Idempotency-Key on guest booking creation', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    $this->postJson('/api/v1/bookings', guestBookingPayload($service))
        ->assertStatus(422)
        ->assertJsonPath('code', 'idempotency_key_required');
});

// --- Abuse controls -------------------------------------------------------

it('caps the number of concurrently open bookings per guest email', function () {
    app(SettingsRepository::class)->set('guest.max_open_bookings', '2', 'integer');

    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    // Pinned to a different date/slot than the booking under test: the
    // factory's random scheduled_date could otherwise collide with it and
    // fail double-booking validation instead of the cap being exercised.
    Booking::factory()->count(2)->guest('dana@example.com')->waitingForQuotation()->create([
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'scheduled_date' => now()->addDays(20)->toDateString(),
        'time_slot_start' => '16:00:00',
        'time_slot_end' => '17:00:00',
    ]);

    $this->postJson('/api/v1/bookings', guestBookingPayload($service), guestIdempotencyHeader())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['guest_email']);
});

it('does not count closed bookings against the per-email cap', function () {
    app(SettingsRepository::class)->set('guest.max_open_bookings', '2', 'integer');

    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    Booking::factory()->count(3)->guest('dana@example.com')->completed()->create([
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'scheduled_date' => now()->addDays(20)->toDateString(),
        'time_slot_start' => '16:00:00',
        'time_slot_end' => '17:00:00',
    ]);

    $this->postJson('/api/v1/bookings', guestBookingPayload($service), guestIdempotencyHeader())
        ->assertCreated();
});

it('rate limits guest booking creation per IP and returns 429', function () {
    app(SettingsRepository::class)->set('guest.rate_limit_per_ip_per_hour', '2', 'integer');
    app(SettingsRepository::class)->set('guest.rate_limit_per_email_per_hour', '50', 'integer');

    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    // Distinct emails so only the per-IP axis can trip.
    foreach (['a@example.com', 'b@example.com'] as $i => $email) {
        $this->postJson('/api/v1/bookings', guestBookingPayload($service, [
            'guest_email' => $email,
            'time_slot_start' => sprintf('%02d:00:00', 9 + $i),
            'time_slot_end' => sprintf('%02d:00:00', 10 + $i),
        ]), guestIdempotencyHeader())->assertCreated();
    }

    $this->postJson('/api/v1/bookings', guestBookingPayload($service, [
        'guest_email' => 'c@example.com',
        'time_slot_start' => '15:00:00',
        'time_slot_end' => '16:00:00',
    ]), guestIdempotencyHeader())->assertStatus(429);
});

it('rate limits guest booking creation per normalized email and returns 429', function () {
    app(SettingsRepository::class)->set('guest.rate_limit_per_ip_per_hour', '50', 'integer');
    app(SettingsRepository::class)->set('guest.rate_limit_per_email_per_hour', '2', 'integer');

    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    foreach ([0, 1] as $i) {
        $this->postJson('/api/v1/bookings', guestBookingPayload($service, [
            'time_slot_start' => sprintf('%02d:00:00', 9 + $i),
            'time_slot_end' => sprintf('%02d:00:00', 10 + $i),
        ]), guestIdempotencyHeader())->assertCreated();
    }

    // Casing/whitespace must not open a fresh bucket — normalization is
    // the same function the cap, claiming and idempotency scoping use.
    $this->postJson('/api/v1/bookings', guestBookingPayload($service, [
        'guest_email' => '  DANA@Example.COM ',
        'time_slot_start' => '15:00:00',
        'time_slot_end' => '16:00:00',
    ]), guestIdempotencyHeader())->assertStatus(429);
});

it('exempts authenticated callers from the guest booking rate limit', function () {
    app(SettingsRepository::class)->set('guest.rate_limit_per_ip_per_hour', '1', 'integer');

    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);
    $address = Address::factory()->for($customer, 'user')->create();

    foreach ([0, 1, 2] as $i) {
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'service_id' => $service->id,
                'address_id' => $address->id,
                'scheduled_date' => now()->addDays(5)->toDateString(),
                'time_slot_start' => sprintf('%02d:00:00', 9 + $i),
                'time_slot_end' => sprintf('%02d:00:00', 10 + $i),
            ], guestIdempotencyHeader())
            ->assertCreated();
    }
});
