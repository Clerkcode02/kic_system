<?php

declare(strict_types=1);

use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('sets baseline security headers on a public, unauthenticated response', function () {
    $response = $this->getJson('/api/v1/categories');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('sets baseline security headers on a protected, authenticated response', function () {
    $user = User::factory()->customer()->create();

    $response = $this->withHeaders(authHeader($user))->getJson('/api/v1/auth/me');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('adds Strict-Transport-Security only over an HTTPS request', function () {
    $response = $this->call('GET', 'https://localhost/api/v1/categories');

    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
