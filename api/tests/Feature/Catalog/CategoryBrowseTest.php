<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The `array` cache store is a container singleton that survives this
    // test's transaction rollback (see AuthenticationFlowTest) — a cached
    // tree from a prior test would otherwise leak in here.
    Cache::flush();
    $this->seed(RoleAndPermissionSeeder::class);
});

function adminForCatalog(): User
{
    $user = User::factory()->admin()->create();
    $user->assignRole(RoleName::Admin->value);

    return $user;
}

it('returns the category tree nested by parent, active only', function () {
    $home = Category::factory()->create(['name' => 'Home Services', 'sort_order' => 1]);
    $plumbing = Category::factory()->child($home)->create(['name' => 'Plumbing', 'sort_order' => 1]);
    Category::factory()->create(['name' => 'Archived', 'is_active' => false, 'sort_order' => 2]);

    $response = $this->getJson('/api/v1/categories')->assertOk();

    $data = $response->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($home->id)
        ->and($data[0]['children'])->toHaveCount(1)
        ->and($data[0]['children'][0]['id'])->toBe($plumbing->id);
});

it('reflects an admin category write immediately, proving cache invalidation', function () {
    $category = Category::factory()->create(['name' => 'Landscaping']);

    $this->getJson('/api/v1/categories')->assertOk()
        ->assertJsonFragment(['name' => 'Landscaping']);

    $admin = adminForCatalog();
    $token = $admin->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/admin/categories/{$category->id}", ['name' => 'Outdoor Services'])
        ->assertOk();

    $this->getJson('/api/v1/categories')->assertOk()
        ->assertJsonFragment(['name' => 'Outdoor Services'])
        ->assertJsonMissing(['name' => 'Landscaping']);
});
