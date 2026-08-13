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

/**
 * Every service gets its own category and business so eager loading is
 * actually exercised — sharing one category/business across all rows
 * would let query count stay flat even with a broken N+1.
 */
function seedServices(int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        Service::factory()->create([
            'category_id' => Category::factory(),
            'business_id' => Business::factory()->verified(),
            'is_active' => true,
        ]);
    }
}

function queryCountForServiceList(): int
{
    DB::flushQueryLog();
    $response = test()->getJson('/api/v1/services')->assertOk();
    $queryCount = count(DB::getQueryLog());

    expect($response->json('data'))->not->toBeEmpty();

    return $queryCount;
}

it('keeps the service list query count constant as result count grows', function () {
    DB::enableQueryLog();

    seedServices(2);
    $queriesFor2 = queryCountForServiceList();

    seedServices(8);
    $queriesFor10 = queryCountForServiceList();

    expect($queriesFor10)
        ->toBe($queriesFor2)
        ->and($queriesFor2)
        ->toBeGreaterThan(0);
});
