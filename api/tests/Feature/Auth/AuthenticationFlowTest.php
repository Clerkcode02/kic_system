<?php

declare(strict_types=1);

use App\Domain\User\Models\User;
use App\Domain\User\Notifications\VerifyEmailNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// The array cache store (and the `auth` rate limiter built on it) is a
// container singleton that outlives a single test's transaction rollback,
// so hits accumulate across `it()` blocks that reuse the same email —
// enough of them across this file trips throttle:auth. Reset it per test.
beforeEach(function () {
    Cache::flush();
    // Registration assigns a spatie role, which must already exist —
    // RefreshDatabase rolls back the seeded rows with every test.
    $this->seed(RoleAndPermissionSeeder::class);
});

const STATEFUL_ORIGIN = 'http://localhost:5173';

/**
 * Extracts the id/hash/expires/signature the notification embedded in its
 * frontend verification link, then turns them into the query the API's
 * signed POST route actually expects.
 *
 * @return array{path: string, query: string}
 */
function capturedVerificationRequest(User $user): array
{
    $captured = null;

    Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user, &$captured) {
        $mail = $notification->toMail($user);
        $url = $mail->actionUrl;

        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);

        preg_match('#/verify-email/([^/]+)/([^/]+)$#', $path, $matches);

        $captured = [
            'id' => $matches[1],
            'hash' => $matches[2],
            'query' => $query,
        ];

        return true;
    });

    return $captured;
}

function verifyEmailFor(User $user): void
{
    $captured = capturedVerificationRequest($user);

    test()->postJson("/api/v1/auth/email/verify/{$captured['id']}/{$captured['hash']}?{$captured['query']}")
        ->assertOk();
}

dataset('registration_roles', ['customer', 'business', 'freelancer']);

it('registers, verifies, logs in via cookie, fetches /me, and logs out', function (string $role) {
    Notification::fake();

    ['endpoint' => $endpoint, 'payload' => $payload] = registrationRequestFor($role);

    $registerResponse = $this->postJson($endpoint, $payload)->assertCreated();
    $email = $registerResponse->json('data.email');
    $user = User::where('email', $email)->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeFalse();

    verifyEmailFor($user);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    forgetAuthGuards();
    $login = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeader('Origin', STATEFUL_ORIGIN)
        ->postJson('/api/v1/auth/login', [
            'email' => $payload['email'],
            'password' => $payload['password'],
        ])
        ->assertOk();

    expect($login->json('data.token'))->toBeNull();

    $sessionCookie = $login->getCookie(config('session.cookie'), false);
    expect($sessionCookie)->not->toBeNull();

    forgetAuthGuards();
    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeader('Origin', STATEFUL_ORIGIN)
        ->withCookie($sessionCookie->getName(), $sessionCookie->getValue())
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $payload['email']);

    forgetAuthGuards();
    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeader('Origin', STATEFUL_ORIGIN)
        ->withCookie($sessionCookie->getName(), $sessionCookie->getValue())
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    forgetAuthGuards();
    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeader('Origin', STATEFUL_ORIGIN)
        ->withCookie($sessionCookie->getName(), $sessionCookie->getValue())
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
})->with('registration_roles');

it('registers, verifies, logs in via bearer token, fetches /me, and logs out', function (string $role) {
    Notification::fake();

    ['endpoint' => $endpoint, 'payload' => $payload] = registrationRequestFor($role);

    $registerResponse = $this->postJson($endpoint, $payload)->assertCreated();
    $email = $registerResponse->json('data.email');
    $user = User::where('email', $email)->firstOrFail();

    verifyEmailFor($user);

    forgetAuthGuards();
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $payload['email'],
        'password' => $payload['password'],
    ])->assertOk();

    $token = $login->json('data.token');
    expect($token)->toBeString()->not->toBeEmpty();

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $payload['email']);

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
})->with('registration_roles');

it('sets business and freelancer accounts to pending verification on registration', function (string $role) {
    Notification::fake();

    ['endpoint' => $endpoint, 'payload' => $payload] = registrationRequestFor($role);

    $this->postJson($endpoint, $payload)->assertCreated();

    $user = User::where('email', $payload['email'])->firstOrFail();

    expect($user->status->value)->toBe('pending');
})->with(['business', 'freelancer']);

it('fires UserRegistered and queues a verification email on every registration flow', function (string $role) {
    Notification::fake();

    ['endpoint' => $endpoint, 'payload' => $payload] = registrationRequestFor($role);

    $this->postJson($endpoint, $payload)->assertCreated();

    $user = User::where('email', $payload['email'])->firstOrFail();

    Notification::assertSentTo($user, VerifyEmailNotification::class);
})->with('registration_roles');

it('rejects registration with a duplicate email', function () {
    ['endpoint' => $endpoint, 'payload' => $payload] = registrationRequestFor('customer');

    $this->postJson($endpoint, $payload)->assertCreated();

    $this->postJson($endpoint, [...$payload, 'phone' => '+14165559999'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects an email verification link with a tampered hash', function () {
    Notification::fake();

    ['endpoint' => $endpoint, 'payload' => $payload] = registrationRequestFor('customer');
    $this->postJson($endpoint, $payload)->assertCreated();
    $user = User::where('email', $payload['email'])->firstOrFail();

    $captured = capturedVerificationRequest($user);

    $this->postJson("/api/v1/auth/email/verify/{$captured['id']}/wrong-hash?{$captured['query']}")
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects login with an incorrect password', function () {
    ['endpoint' => $endpoint, 'payload' => $payload] = registrationRequestFor('customer');
    $this->postJson($endpoint, $payload)->assertCreated();

    $this->postJson('/api/v1/auth/login', [
        'email' => $payload['email'],
        'password' => 'not-the-right-password',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('rejects login for a suspended account', function () {
    $user = User::factory()->customer()->suspended()->create();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('rejects /auth/me without credentials', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('resends the verification email for an unverified account', function () {
    Notification::fake();

    ['endpoint' => $endpoint, 'payload' => $payload] = registrationRequestFor('customer');
    $this->postJson($endpoint, $payload)->assertCreated();

    $this->postJson('/api/v1/auth/email/resend', ['email' => $payload['email']])
        ->assertOk();

    $user = User::where('email', $payload['email'])->firstOrFail();

    Notification::assertSentTo($user, VerifyEmailNotification::class, 2);
});
