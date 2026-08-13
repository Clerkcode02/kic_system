<?php

declare(strict_types=1);

use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Models\ServicePricingTier;
use App\Support\ValueObjects\Money;
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
