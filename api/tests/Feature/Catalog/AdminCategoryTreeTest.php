<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

it('returns the full category tree, including inactive categories, via GET /api/v1/admin/categories', function () {
    // A handful of Feature tests (see ConcurrentBookingTest) intentionally
    // commit a row outside RefreshDatabase that this test's own transaction
    // can never delete (FK-chained down to an append-only booking history
    // row) — so assert against the same live table the query reads rather
    // than a hardcoded top-level count or list position.
    $baselineTopLevel = Category::query()->whereNull('parent_id')->count();

    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);

    $parent = Category::factory()->create(['name' => 'Home', 'sort_order' => 0]);
    $child = Category::factory()->child($parent)->create(['name' => 'Plumbing', 'sort_order' => 0]);
    Category::factory()->create(['name' => 'Inactive Category', 'is_active' => false, 'sort_order' => 1]);

    $response = $this->withHeaders(authHeader($admin))
        ->getJson('/api/v1/admin/categories')
        ->assertOk()
        ->assertJsonCount($baselineTopLevel + 2, 'data')
        ->assertJsonFragment(['name' => 'Inactive Category', 'is_active' => false]);

    $home = collect($response->json('data'))->firstWhere('id', $parent->id);

    expect($home)->not->toBeNull()
        ->and(collect($home['children'])->pluck('id')->all())->toBe([$child->id]);
});

it('returns exactly the top-level categories that exist in the database', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);

    // Same leaked-row caveat as above — assert the response mirrors the
    // live table exactly (identity, not just count), not that the table is
    // literally empty.
    $expectedIds = Category::query()->whereNull('parent_id')->pluck('id')->sort()->values()->all();

    $response = $this->withHeaders(authHeader($admin))
        ->getJson('/api/v1/admin/categories')
        ->assertOk();

    $actualIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();

    expect($actualIds)->toBe($expectedIds);
});

it('rejects listing admin categories without authentication', function () {
    $this->getJson('/api/v1/admin/categories')->assertUnauthorized();
});

it('denies listing admin categories to a non-admin role', function () {
    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);

    $this->withHeaders(authHeader($customer))
        ->getJson('/api/v1/admin/categories')
        ->assertForbidden();
});
