<?php

declare(strict_types=1);

use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// See AuthenticationFlowTest — the `array` cache store backing throttle:auth
// is a container singleton that survives this test's transaction rollback.
beforeEach(function () {
    Cache::flush();
    $this->seed(RoleAndPermissionSeeder::class);
});

function tokenForDevice(User $user, string $device): string
{
    forgetAuthGuards();

    $response = test()->withHeader('X-Device-Name', $device)
        ->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

    return $response->json('data.token');
}

it('revokes only the calling device when logging out, leaving other devices authenticated', function () {
    Notification::fake();
    $user = User::factory()->customer()->create(['password' => 'password']);

    $tokenA = tokenForDevice($user, 'Device A — iPhone');
    $tokenB = tokenForDevice($user, 'Device B — Chrome on Mac');

    expect($user->tokens()->count())->toBe(2);
    expect($user->tokens()->pluck('name')->all())->toContain('Device A — iPhone', 'Device B — Chrome on Mac');

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$tokenA}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$tokenA}")
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$tokenB}")
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);

    expect($user->tokens()->count())->toBe(1);
});

it('revokes every device on logout-all-devices', function () {
    Notification::fake();
    $user = User::factory()->customer()->create(['password' => 'password']);

    $tokenA = tokenForDevice($user, 'Device A — iPhone');
    $tokenB = tokenForDevice($user, 'Device B — Chrome on Mac');

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$tokenA}")
        ->postJson('/api/v1/auth/logout-all-devices')
        ->assertOk();

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$tokenA}")
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$tokenB}")
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();

    expect($user->tokens()->count())->toBe(0);
});
