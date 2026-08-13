<?php

declare(strict_types=1);

use App\Domain\Business\Models\Business;
use App\Domain\Business\Services\FakeGeocoder;
use App\Domain\Business\Services\Geocoder;
use App\Domain\Business\ValueObjects\Coordinates;
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
function providerOwnerWithToken(): array
{
    $user = User::factory()->provider()->create();
    $user->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->verified()->create(['user_id' => $user->id]);
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $business, $token];
}

it('returns the authenticated provider\'s business profile', function () {
    [, $business, $token] = providerOwnerWithToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/provider/me')
        ->assertOk()
        ->assertJsonPath('data.id', $business->id)
        ->assertJsonPath('data.legal_name', $business->legal_name);
});

it('updates business_hours and max_bookings_per_day', function () {
    [, , $token] = providerOwnerWithToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson('/api/v1/provider/me', [
            'max_bookings_per_day' => 25,
            'business_hours' => ['mon' => ['08:00', '18:00']],
        ])
        ->assertOk()
        ->assertJsonPath('data.max_bookings_per_day', 25);
});

it('sets location directly from lat/lng without calling the geocoder', function () {
    $fake = new FakeGeocoder();
    $this->app->instance(Geocoder::class, $fake);

    [, , $token] = providerOwnerWithToken();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson('/api/v1/provider/me', ['lat' => 43.6532, 'lng' => -79.3832])
        ->assertOk();

    expect((float) $response->json('data.location.lat'))->toEqualWithDelta(43.6532, 0.0001)
        ->and((float) $response->json('data.location.lng'))->toEqualWithDelta(-79.3832, 0.0001)
        ->and($fake->recordedCalls)->toBeEmpty();
});

it('geocodes the address (Canada-filtered) when no coordinates are given', function () {
    $fake = (new FakeGeocoder())->returning(new Coordinates(45.4215, -75.6972));
    $this->app->instance(Geocoder::class, $fake);

    [, , $token] = providerOwnerWithToken();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson('/api/v1/provider/me', [
            'street' => '123 Sparks St',
            'city' => 'Ottawa',
            'province' => 'ON',
            'postal_code' => 'K1P 5B4',
        ])
        ->assertOk();

    expect((float) $response->json('data.location.lat'))->toEqualWithDelta(45.4215, 0.0001)
        ->and((float) $response->json('data.location.lng'))->toEqualWithDelta(-75.6972, 0.0001);

    expect($fake->recordedCalls)->toHaveCount(1)
        ->and($fake->recordedCalls[0]['country_filter'])->toBe('ca')
        ->and($fake->recordedCalls[0]['address'])->toContain('Ottawa');
});

it('rejects coordinates outside Canada\'s bounding box', function () {
    [, , $token] = providerOwnerWithToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson('/api/v1/provider/me', ['lat' => 10.0, 'lng' => 10.0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lat', 'lng']);
});

it('rejects an invalid province code', function () {
    [, , $token] = providerOwnerWithToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson('/api/v1/provider/me', [
            'street' => '123 Sparks St',
            'city' => 'Ottawa',
            'province' => 'ZZ',
            'postal_code' => 'K1P 5B4',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['province']);
});

it('rejects an invalid Canadian postal code format', function () {
    [, , $token] = providerOwnerWithToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson('/api/v1/provider/me', [
            'street' => '123 Sparks St',
            'city' => 'Ottawa',
            'province' => 'ON',
            'postal_code' => '90210',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['postal_code']);
});

it('denies a customer from updating a business profile', function () {
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson('/api/v1/provider/me', ['max_bookings_per_day' => 5])
        ->assertForbidden();
});

it('rejects an unauthenticated request', function () {
    $this->getJson('/api/v1/provider/me')->assertUnauthorized();
});
