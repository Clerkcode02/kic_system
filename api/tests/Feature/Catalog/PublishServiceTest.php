<?php

declare(strict_types=1);

use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Models\Category;
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

function verifiedProviderWithToken(): array
{
    $user = User::factory()->provider()->create();
    $user->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->verified()->create(['user_id' => $user->id]);
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $business, $token];
}

it('lets a verified provider publish a service with pricing tiers', function () {
    [, , $token] = verifiedProviderWithToken();
    $category = Category::factory()->create();

    $payload = [
        'category_id' => $category->id,
        'title' => 'Deep Clean',
        'description' => 'A thorough top-to-bottom clean.',
        'pricing_type' => 'package',
        'base_price' => 149.00,
        'estimated_duration_minutes' => 120,
        'pricing_tiers' => [
            ['tier_name' => 'Basic', 'price' => 99.00],
            ['tier_name' => 'Premium', 'price' => 199.00],
        ],
    ];

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/provider/services', $payload)
        ->assertCreated()
        ->assertJsonPath('data.title', 'Deep Clean')
        ->assertJsonCount(2, 'data.pricing_tiers');
});

it('blocks an unverified provider from publishing a service', function () {
    $user = User::factory()->provider()->create();
    $user->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->pending()->create(['user_id' => $user->id]);
    $token = $user->createToken('test')->plainTextToken;

    $category = Category::factory()->create();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/provider/services', [
            'category_id' => $category->id,
            'title' => 'Deep Clean',
            'description' => 'A thorough top-to-bottom clean.',
            'pricing_type' => 'fixed',
            'base_price' => 149.00,
            'estimated_duration_minutes' => 120,
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'business_not_verified');
});

it('denies a customer from publishing a service', function () {
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/provider/services', ['title' => 'x'])
        ->assertForbidden();
});
