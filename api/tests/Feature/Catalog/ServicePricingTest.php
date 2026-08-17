<?php

declare(strict_types=1);

use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Models\ServicePricingTier;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use App\Support\ValueObjects\Money;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

it('returns the estimated pricing for a service, including its tiers', function () {
    $business = Business::factory()->verified()->create();
    $category = Category::factory()->create();
    $service = Service::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'base_price' => Money::fromMinorUnits(9_900, 'CAD'),
    ]);

    ServicePricingTier::factory()->create([
        'service_id' => $service->id,
        'tier_name' => 'Basic',
        'price' => Money::fromMinorUnits(9_900, 'CAD'),
        'sort_order' => 0,
    ]);
    ServicePricingTier::factory()->create([
        'service_id' => $service->id,
        'tier_name' => 'Premium',
        'price' => Money::fromMinorUnits(19_900, 'CAD'),
        'sort_order' => 1,
    ]);

    $response = $this->getJson("/api/v1/services/{$service->id}/pricing")->assertOk();

    $response->assertJsonPath('data.service_id', $service->id)
        ->assertJsonPath('data.base_price', '99.00')
        ->assertJsonPath('data.currency', 'CAD')
        ->assertJsonCount(2, 'data.pricing_tiers')
        ->assertJsonPath('data.pricing_tiers.0.tier_name', 'Basic')
        ->assertJsonPath('data.pricing_tiers.1.tier_name', 'Premium');
});

it('returns 404 for a pricing lookup on a nonexistent service', function () {
    $this->getJson('/api/v1/services/019ff912-01cd-7278-99c5-6c3e91b639a1/pricing')
        ->assertNotFound();
});

it('reflects an owner-provider pricing update immediately, proving ServicePricingCache invalidation', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $owner = User::factory()->provider()->create();
    $owner->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->verified()->create(['user_id' => $owner->id]);
    $category = Category::factory()->create();
    $service = Service::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
    ]);
    ServicePricingTier::factory()->create([
        'service_id' => $service->id,
        'tier_name' => 'Basic',
        'sort_order' => 0,
    ]);

    // Warm the cache.
    $this->getJson("/api/v1/services/{$service->id}/pricing")->assertOk()
        ->assertJsonPath('data.pricing_tiers.0.tier_name', 'Basic');

    $token = $owner->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/provider/services/{$service->id}", [
            'pricing_tiers' => [
                ['tier_name' => 'Deluxe', 'price' => '150.00', 'sort_order' => 0],
            ],
        ])
        ->assertOk();

    $this->getJson("/api/v1/services/{$service->id}/pricing")->assertOk()
        ->assertJsonCount(1, 'data.pricing_tiers')
        ->assertJsonPath('data.pricing_tiers.0.tier_name', 'Deluxe');
});

it('makes a deactivated service unreachable immediately, proving ServicePricingCache invalidation on deactivate', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $owner = User::factory()->provider()->create();
    $owner->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->verified()->create(['user_id' => $owner->id]);
    $category = Category::factory()->create();
    $service = Service::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    // Warm the cache while the service is still active.
    $this->getJson("/api/v1/services/{$service->id}/pricing")->assertOk();

    $token = $owner->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/provider/services/{$service->id}")
        ->assertNoContent();

    $this->getJson("/api/v1/services/{$service->id}/pricing")->assertNotFound();
});
