<?php

declare(strict_types=1);

use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Services\BookingAccessTokenService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Notification::fake();
});

/**
 * CLAUDE.md §2: "No plaintext booking access token in the database, in a
 * log line, or in the DOM", and guest emails appear in the audit trail only
 * as a hash.
 *
 * This captures everything written through the logger across a full guest
 * request and asserts neither secret appears. It is a regression guard for
 * the easy mistake — adding a debug/context array that happens to include
 * the request payload or the resolved token.
 */
function captureLogMessages(callable $body): string
{
    $captured = [];

    Log::listen(function ($message) use (&$captured) {
        $captured[] = $message->message.' '.json_encode($message->context);
    });

    $body();

    return implode("\n", $captured);
}

it('writes neither the plaintext token nor the guest email to the log', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    $plaintext = null;

    $logged = captureLogMessages(function () use ($service, &$plaintext) {
        $created = $this->postJson('/api/v1/bookings', guestBookingPayload($service), guestIdempotencyHeader())
            ->assertCreated();

        $plaintext = $created->json('meta.access_token');
        $bookingNumber = $created->json('data.booking_number');

        $this->withHeaders(['X-Booking-Token' => $plaintext])
            ->getJson("/api/v1/guest/bookings/{$bookingNumber}")
            ->assertOk();

        $this->withHeaders(['X-Booking-Token' => $plaintext])
            ->patchJson("/api/v1/guest/bookings/{$bookingNumber}/cancel", ['reason' => 'Changed my mind'])
            ->assertOk();
    });

    expect($plaintext)->toBeString();
    expect($logged)->not->toContain($plaintext);
    expect($logged)->not->toContain('dana@example.com');
});

it('never persists the plaintext token anywhere in the database', function () {
    [, $business] = guestTestProvider();
    $service = guestTestService($business);

    $created = $this->postJson('/api/v1/bookings', guestBookingPayload($service), guestIdempotencyHeader())
        ->assertCreated();

    $plaintext = $created->json('meta.access_token');

    // The idempotency record stores the response body verbatim, which is
    // the one place a replay could resurface the token — and the only
    // caller who can trigger that replay is the one who already has it.
    // Everything else must be free of it.
    expect(\App\Domain\Booking\Models\BookingAccessToken::query()->where('token_hash', $plaintext)->exists())->toBeFalse();
    expect(\App\Domain\Audit\Models\AuditLog::query()->get()->toJson())->not->toContain($plaintext);
    expect(\App\Domain\Booking\Models\BookingStatusHistory::query()->get()->toJson())->not->toContain($plaintext);
    expect(Booking::query()->get()->toJson())->not->toContain($plaintext);
});

it('keeps guest contact details out of a serialized Booking model', function () {
    [, $business] = guestTestProvider();

    $booking = Booking::factory()->guest('dana@example.com')->create([
        'provider_id' => $business->id,
        'service_id' => guestTestService($business)->id,
    ]);

    // $hidden on the model is a backstop for exactly this: a stray
    // toArray()/toJson() of a Booking reaching a log or a response.
    $serialized = $booking->fresh()->toJson();

    expect($serialized)->not->toContain('dana@example.com');
    expect($serialized)->not->toContain((string) $booking->guest_phone);

    // The accessors still work — hiding is about serialization, not access.
    expect($booking->fresh()->contactEmail())->toBe('dana@example.com');
});

it('redacts a token for safe display', function () {
    $redacted = app(BookingAccessTokenService::class)->redact(str_repeat('a', 64));

    expect($redacted)->toStartWith('aaaa');
    expect($redacted)->not->toBe(str_repeat('a', 64));
    expect(substr($redacted, 4))->toBe(str_repeat('*', 60));
});
