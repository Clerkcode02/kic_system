<?php

declare(strict_types=1);

use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Service;
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
function providerServiceOwnerToken(): array
{
    $user = User::factory()->provider()->create();
    $user->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->verified()->create(['user_id' => $user->id]);
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $business, $token];
}

it('lists only the authenticated provider\'s own services', function () {
    [, $business, $token] = providerServiceOwnerToken();
    $category = Category::factory()->create();

    Service::factory()->count(2)->create(['business_id' => $business->id, 'category_id' => $category->id]);
    Service::factory()->create(); // another business's service

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/provider/me/services')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('shows a single owned service', function () {
    [, $business, $token] = providerServiceOwnerToken();
    $service = Service::factory()->create(['business_id' => $business->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/provider/services/{$service->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $service->id);
});

it('denies showing another provider\'s service', function () {
    [, , $token] = providerServiceOwnerToken();
    $otherService = Service::factory()->create();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/provider/services/{$otherService->id}")
        ->assertForbidden();
});

it('updates a service and replaces its pricing tiers', function () {
    [, $business, $token] = providerServiceOwnerToken();
    $service = Service::factory()->create(['business_id' => $business->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/provider/services/{$service->id}", [
            'title' => 'Updated Title',
            'pricing_tiers' => [
                ['tier_name' => 'Standard', 'price' => 120.00],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated Title')
        ->assertJsonCount(1, 'data.pricing_tiers')
        ->assertJsonPath('data.pricing_tiers.0.tier_name', 'Standard');
});

it('denies updating another provider\'s service', function () {
    [, , $token] = providerServiceOwnerToken();
    $otherService = Service::factory()->create();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/provider/services/{$otherService->id}", ['title' => 'Hijacked'])
        ->assertForbidden();
});

it('deactivates a service instead of deleting it', function () {
    [, $business, $token] = providerServiceOwnerToken();
    $service = Service::factory()->create(['business_id' => $business->id, 'is_active' => true]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/provider/services/{$service->id}")
        ->assertNoContent();

    expect($service->fresh()->is_active)->toBeFalse();
});

it('rejects invalid input when updating a service', function () {
    [, $business, $token] = providerServiceOwnerToken();
    $service = Service::factory()->create(['business_id' => $business->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/provider/services/{$service->id}", ['base_price' => -5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['base_price']);
});

it('rejects an unauthenticated request', function () {
    $service = Service::factory()->create();

    $this->getJson("/api/v1/provider/services/{$service->id}")->assertUnauthorized();
});
