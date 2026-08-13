<?php

declare(strict_types=1);

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\ProviderAvailability;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->seed(RoleAndPermissionSeeder::class);
});

/**
 * @return array{0: User, 1: Business, 2: string}
 */
function providerAvailabilityOwnerToken(): array
{
    $user = User::factory()->provider()->create();
    $user->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->verified()->create(['user_id' => $user->id]);
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $business, $token];
}

it('returns the weekly schedule and date overrides', function () {
    [, $business, $token] = providerAvailabilityOwnerToken();
    ProviderAvailability::factory()->create(['business_id' => $business->id, 'day_of_week' => 1]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/provider/me/availability')
        ->assertOk()
        ->assertJsonCount(1, 'data.weekly');
});

it('replaces the weekly schedule and overrides', function () {
    [, , $token] = providerAvailabilityOwnerToken();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/provider/me/availability', [
            'weekly' => [
                ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
                ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00'],
            ],
            'overrides' => [
                ['date' => now()->addWeek()->toDateString(), 'is_blackout' => true],
            ],
        ])
        ->assertOk();

    expect($response->json('data.weekly'))->toHaveCount(2)
        ->and($response->json('data.overrides'))->toHaveCount(1)
        ->and($response->json('data.overrides.0.is_blackout'))->toBeTrue();
});

it('rejects a weekly entry where end_time is before start_time', function () {
    [, , $token] = providerAvailabilityOwnerToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/provider/me/availability', [
            'weekly' => [
                ['day_of_week' => 1, 'start_time' => '17:00', 'end_time' => '09:00'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['weekly.0.end_time']);
});

it('rejects a non-blackout override missing start/end times', function () {
    [, , $token] = providerAvailabilityOwnerToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/provider/me/availability', [
            'overrides' => [
                ['date' => now()->addWeek()->toDateString(), 'is_blackout' => false],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['overrides.0.start_time']);
});

it('denies a customer from managing availability', function () {
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/provider/me/availability')
        ->assertForbidden();
});

it('rejects an unauthenticated request', function () {
    $this->getJson('/api/v1/provider/me/availability')->assertUnauthorized();
});
