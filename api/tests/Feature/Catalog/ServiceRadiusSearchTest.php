<?php

declare(strict_types=1);

use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

// Toronto — same reference point the seeder uses.
const CITY_LAT = 43.6532;
const CITY_LNG = -79.3832;

function placeBusinessAt(Business $business, float $lat, float $lng): void
{
    DB::statement(
        'UPDATE businesses SET location = ST_MakePoint(?, ?)::geography WHERE id = ?',
        [$lng, $lat, $business->id]
    );
}

it('includes a business inside the radius and excludes one outside it, via PostGIS ST_DWithin', function () {
    $category = Category::factory()->create();

    $nearBusiness = Business::factory()->verified()->create();
    placeBusinessAt($nearBusiness, CITY_LAT, CITY_LNG);
    $nearService = Service::factory()->create([
        'business_id' => $nearBusiness->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    // ~1 degree of latitude away (~111km) — well outside a 25km search radius.
    $farBusiness = Business::factory()->verified()->create();
    placeBusinessAt($farBusiness, CITY_LAT + 1.0, CITY_LNG);
    $farService = Service::factory()->create([
        'business_id' => $farBusiness->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/services?lat='.CITY_LAT.'&lng='.CITY_LNG.'&radius=25000')->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($nearService->id)
        ->not->toContain($farService->id);
});
