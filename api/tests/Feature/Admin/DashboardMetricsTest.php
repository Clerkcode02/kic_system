<?php

declare(strict_types=1);

use App\Domain\Reporting\Models\AdminAnalyticsSnapshot;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

it('returns the recent snapshot history for an admin', function () {
    AdminAnalyticsSnapshot::factory()->count(3)->create();

    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);
    $token = $admin->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/dashboard/metrics')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('denies a non-admin from viewing analytics', function () {
    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);
    $token = $customer->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/dashboard/metrics')
        ->assertForbidden();
});
