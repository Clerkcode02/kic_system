<?php

declare(strict_types=1);

use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

it('browses services by category, including services under child categories', function () {
    $parent = Category::factory()->create(['name' => 'Home Services']);
    $child = Category::factory()->child($parent)->create(['name' => 'Plumbing']);
    $unrelated = Category::factory()->create(['name' => 'Digital Services']);

    $business = Business::factory()->verified()->create();

    $inParent = Service::factory()->create(['business_id' => $business->id, 'category_id' => $parent->id, 'is_active' => true]);
    $inChild = Service::factory()->create(['business_id' => $business->id, 'category_id' => $child->id, 'is_active' => true]);
    $inUnrelated = Service::factory()->create(['business_id' => $business->id, 'category_id' => $unrelated->id, 'is_active' => true]);

    $response = $this->getJson("/api/v1/services?category={$parent->slug}")->assertOk();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($inParent->id)
        ->toContain($inChild->id)
        ->not->toContain($inUnrelated->id);
});

it('excludes inactive services and services from unverified businesses', function () {
    $category = Category::factory()->create();
    $verifiedBusiness = Business::factory()->verified()->create();
    $pendingBusiness = Business::factory()->pending()->create();

    $active = Service::factory()->create(['business_id' => $verifiedBusiness->id, 'category_id' => $category->id, 'is_active' => true]);
    $inactive = Service::factory()->create(['business_id' => $verifiedBusiness->id, 'category_id' => $category->id, 'is_active' => false]);
    $unverified = Service::factory()->create(['business_id' => $pendingBusiness->id, 'category_id' => $category->id, 'is_active' => true]);

    $ids = collect($this->getJson('/api/v1/services')->assertOk()->json('data'))->pluck('id');

    expect($ids)->toContain($active->id)
        ->not->toContain($inactive->id)
        ->not->toContain($unverified->id);
});

it('returns 422 for an out-of-range coordinate', function () {
    $this->getJson('/api/v1/services?lat=200&lng=-79.38')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lat']);
});
