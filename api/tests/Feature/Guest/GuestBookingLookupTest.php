<?php

declare(strict_types=1);

use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAccessToken;
use App\Domain\Booking\Notifications\GuestBookingTrackingNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Notification::fake();
});

/**
 * SRS §6.1: the lookup endpoint must be useless for enumeration. A caller
 * who guesses a booking number learns nothing — the status, body and shape
 * are fixed before the query runs, and only the mailbox owner is told
 * anything.
 */
it('returns a byte-identical 202 for a real and a fake booking number', function () {
    [, $business] = guestTestProvider();

    $real = Booking::factory()->guest('dana@example.com')->waitingForQuotation()->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
    ]);

    $hit = $this->postJson('/api/v1/guest/bookings/lookup', [
        'booking_number' => $real->booking_number,
        'email' => 'dana@example.com',
    ])->assertStatus(202);

    $missByNumber = $this->postJson('/api/v1/guest/bookings/lookup', [
        'booking_number' => 'BK-000000NOPE',
        'email' => 'dana@example.com',
    ])->assertStatus(202);

    $missByEmail = $this->postJson('/api/v1/guest/bookings/lookup', [
        'booking_number' => $real->booking_number,
        'email' => 'someone-else@example.com',
    ])->assertStatus(202);

    expect($missByNumber->getContent())->toBe($hit->getContent());
    expect($missByEmail->getContent())->toBe($hit->getContent());
});

it('emails a fresh tracking link only on a match', function () {
    [, $business] = guestTestProvider();

    $booking = Booking::factory()->guest('dana@example.com')->waitingForQuotation()->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
    ]);

    $this->postJson('/api/v1/guest/bookings/lookup', [
        'booking_number' => 'BK-000000NOPE',
        'email' => 'dana@example.com',
    ])->assertStatus(202);

    Notification::assertNothingSent();
    expect(BookingAccessToken::query()->count())->toBe(0);

    $this->postJson('/api/v1/guest/bookings/lookup', [
        'booking_number' => $booking->booking_number,
        'email' => 'DANA@Example.com ',
    ])->assertStatus(202);

    // Case and whitespace are normalized the same way everywhere, so a
    // guest typing their address differently still finds their booking.
    Notification::assertSentTo(
        new AnonymousNotifiable(),
        GuestBookingTrackingNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'dana@example.com',
    );

    expect(BookingAccessToken::query()->where('booking_id', $booking->id)->count())->toBe(1);
});

it('does not re-issue a link for a closed booking', function () {
    [, $business] = guestTestProvider();

    $cancelled = Booking::factory()->guest('dana@example.com')->cancelled()->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
    ]);

    $this->postJson('/api/v1/guest/bookings/lookup', [
        'booking_number' => $cancelled->booking_number,
        'email' => 'dana@example.com',
    ])->assertStatus(202);

    Notification::assertNothingSent();
    expect(BookingAccessToken::query()->count())->toBe(0);
});

it('never matches a registered customer\'s booking through the guest lookup', function () {
    [, $business] = guestTestProvider();

    $customerBooking = Booking::factory()->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
    ]);

    $this->postJson('/api/v1/guest/bookings/lookup', [
        'booking_number' => $customerBooking->booking_number,
        'email' => $customerBooking->customer->email,
    ])->assertStatus(202);

    // Registered bookings live behind the account, not behind a token —
    // the guest lookup must not become a back door into them.
    Notification::assertNothingSent();
    expect(BookingAccessToken::query()->count())->toBe(0);
});

it('validates only shape, never existence', function () {
    $this->postJson('/api/v1/guest/bookings/lookup', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['booking_number', 'email']);

    // A well-formed but nonexistent pair is a 202, not a 404 or a
    // validation error — no `exists:` rule may appear on this endpoint.
    $this->postJson('/api/v1/guest/bookings/lookup', [
        'booking_number' => 'BK-999999ZZZZ',
        'email' => 'nobody@example.com',
    ])->assertStatus(202);
});
