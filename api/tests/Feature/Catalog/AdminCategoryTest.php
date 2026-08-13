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

function tokenFor(User $user): string
{
    return $user->createToken('test')->plainTextToken;
}

function admin(): User
{
    $user = User::factory()->admin()->create();
    $user->assignRole(RoleName::Admin->value);

    return $user;
}

function customerNonAdmin(): User
{
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);

    return $user;
}

it('lets an admin create a category', function () {
    $token = tokenFor(admin());

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/admin/categories', ['name' => 'Home Services'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Home Services')
        ->assertJsonPath('data.slug', 'home-services');
});

it('denies category creation to a non-admin', function () {
    $token = tokenFor(customerNonAdmin());

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/admin/categories', ['name' => 'Home Services'])
        ->assertForbidden();
});

it('rejects category creation without authentication', function () {
    $this->postJson('/api/v1/admin/categories', ['name' => 'Home Services'])
        ->assertUnauthorized();
});

it('validates category creation input', function () {
    $token = tokenFor(admin());

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/admin/categories', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('lets an admin update a category', function () {
    $category = Category::factory()->create(['name' => 'Old Name']);
    $token = tokenFor(admin());

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/admin/categories/{$category->id}", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');
});

it('rejects moving a category under its own descendant', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->child($parent)->create();
    $token = tokenFor(admin());

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/admin/categories/{$parent->id}", ['parent_id' => $child->id])
        ->assertStatus(409)
        ->assertJsonPath('code', 'category_cycle');
});

it('lets an admin delete an unused category', function () {
    $category = Category::factory()->create();
    $token = tokenFor(admin());

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/admin/categories/{$category->id}")
        ->assertNoContent();

    expect(Category::find($category->id))->toBeNull();
});

it('blocks deleting a category that still has services', function () {
    $category = Category::factory()->create();
    $business = Business::factory()->verified()->create();
    Service::factory()->create(['category_id' => $category->id, 'business_id' => $business->id]);

    $token = tokenFor(admin());

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/admin/categories/{$category->id}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'category_in_use');
});

it('blocks deleting a category that still has subcategories', function () {
    $parent = Category::factory()->create();
    Category::factory()->child($parent)->create();

    $token = tokenFor(admin());

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/admin/categories/{$parent->id}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'category_has_children');
});

it('lets an admin reorder sibling categories', function () {
    $first = Category::factory()->create(['sort_order' => 0]);
    $second = Category::factory()->create(['sort_order' => 1]);

    $token = tokenFor(admin());

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/admin/categories/reorder', [
            'categories' => [
                ['id' => $first->id, 'sort_order' => 1],
                ['id' => $second->id, 'sort_order' => 0],
            ],
        ])
        ->assertOk();

    expect($first->fresh()->sort_order)->toBe(1)
        ->and($second->fresh()->sort_order)->toBe(0);
});
