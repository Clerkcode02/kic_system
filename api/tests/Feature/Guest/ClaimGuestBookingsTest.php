<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAccessToken;
use App\Domain\Booking\Services\BookingAccessTokenService;
use App\Domain\User\Actions\VerifyEmail;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Notification::fake();
});

function unverifiedCustomer(string $email): User
{
    $user = User::factory()->customer()->unverified()->create(['email' => $email]);
    $user->assignRole(RoleName::Customer->value);

    return $user;
}

/**
 * SRS §6.1 "Claiming": on email verification only — never on registration,
 * never on login. Anyone can type any address into a signup form; only
 * proving control of the mailbox may hand over bookings carrying payments,
 * a service address and a phone number.
 */
it('claims matching guest bookings when the account verifies its email', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    $mine = Booking::factory()->count(2)->guest('dana@example.com')->waitingForQuotation()->create([
        'provider_id' => $business->id,
        'service_id' => $service->id,
    ]);

    $someoneElses = Booking::factory()->guest('other@example.com')->waitingForQuotation()->create([
        'provider_id' => $business->id,
        'service_id' => $service->id,
    ]);

    $tokens = app(BookingAccessTokenService::class);
    $liveToken = $tokens->issue($mine->first());
    $tokens->issue($someoneElses);

    $user = unverifiedCustomer('dana@example.com');

    app(VerifyEmail::class)->handle($user);

    foreach ($mine as $booking) {
        $booking->refresh();

        expect($booking->customer_id)->toBe($user->id);
        expect($booking->claimed_by_user_id)->toBe($user->id);
        expect($booking->claimed_at)->not->toBeNull();
        expect($booking->isGuest())->toBeFalse();

        // The guest columns must be cleared in the same write — otherwise
        // the exactly-one-actor CHECK would have rejected the update.
        expect($booking->guest_email)->toBeNull();
        expect($booking->guest_email_normalized)->toBeNull();
    }

    // Another guest's booking is untouched.
    expect($someoneElses->fresh()->customer_id)->toBeNull();

    // Claimed bookings' tokens are revoked; the other guest's still works.
    expect(BookingAccessToken::query()->where('booking_id', $mine->first()->id)->first()->revoked_at)->not->toBeNull();
    expect(BookingAccessToken::query()->where('booking_id', $someoneElses->id)->first()->revoked_at)->toBeNull();

    // And the revoked token is dead over HTTP, not just in the database.
    $this->withHeaders(['X-Booking-Token' => $liveToken['plaintext']])
        ->getJson("/api/v1/guest/bookings/{$mine->first()->booking_number}")
        ->assertNotFound();
});

it('claims nothing for an account that registered but never verified', function () {
    [, $business] = guestTestProvider();

    $booking = Booking::factory()->guest('dana@example.com')->waitingForQuotation()->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
    ]);

    // Registration alone.
    $this->postJson('/api/v1/auth/register/customer', [
        'name' => 'Dana Okafor',
        'email' => 'dana@example.com',
        'phone' => '+14165550143',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertCreated();

    expect($booking->fresh()->customer_id)->toBeNull();
    expect($booking->fresh()->isGuest())->toBeTrue();

    // Logging in changes nothing either.
    $this->postJson('/api/v1/auth/login', [
        'email' => 'dana@example.com',
        'password' => 'Password123!',
    ]);

    expect($booking->fresh()->customer_id)->toBeNull();
});

it('matches on the normalized email, not the raw one', function () {
    [, $business] = guestTestProvider();

    $booking = Booking::factory()->guest()->waitingForQuotation()->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
        'guest_email' => 'Dana.Okafor@Example.COM',
        'guest_email_normalized' => 'dana.okafor@example.com',
    ]);

    $user = unverifiedCustomer('DANA.OKAFOR@example.com');

    app(VerifyEmail::class)->handle($user);

    expect($booking->fresh()->customer_id)->toBe($user->id);
});

it('records an audit entry for the claim without leaking the email', function () {
    [, $business] = guestTestProvider();

    $booking = Booking::factory()->guest('dana@example.com')->waitingForQuotation()->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
    ]);

    $user = unverifiedCustomer('dana@example.com');

    app(VerifyEmail::class)->handle($user);

    $entry = AuditLog::query()
        ->where('action', 'booking.guest_bookings_claimed')
        ->firstOrFail();

    expect($entry->actor_id)->toBe($user->id);
    expect($entry->after_state['claimed_bookings'])->toBe(1);
    expect($entry->after_state['booking_numbers'])->toBe([$booking->booking_number]);
    expect($entry->after_state['access_tokens_revoked'])->toBeInt();
    expect($entry->toJson())->not->toContain('dana@example.com');
});

it('is idempotent — verifying again claims nothing further', function () {
    [, $business] = guestTestProvider();

    Booking::factory()->guest('dana@example.com')->waitingForQuotation()->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
    ]);

    $user = unverifiedCustomer('dana@example.com');

    app(VerifyEmail::class)->handle($user);
    app(VerifyEmail::class)->handle($user);

    expect(AuditLog::query()->where('action', 'booking.guest_bookings_claimed')->count())->toBe(1);
});
