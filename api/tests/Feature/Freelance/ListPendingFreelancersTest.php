<?php

declare(strict_types=1);

use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

it('lists pending freelancer applications by default via GET /api/v1/admin/freelancers/verification-queue', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);

    $pending = FreelancerProfile::factory()->pending()->create();
    FreelancerProfile::factory()->approved()->create();
    FreelancerProfile::factory()->rejected()->create();

    $this->withHeaders(authHeader($admin))
        ->getJson('/api/v1/admin/freelancers/verification-queue')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pending->id);
});

it('filters the verification queue by an explicit status', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);

    FreelancerProfile::factory()->pending()->create();
    $approved = FreelancerProfile::factory()->approved()->create();

    $this->withHeaders(authHeader($admin))
        ->getJson('/api/v1/admin/freelancers/verification-queue?status=approved')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $approved->id);
});

it('returns an empty queue when there are no pending freelancers', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);

    FreelancerProfile::factory()->approved()->create();

    $this->withHeaders(authHeader($admin))
        ->getJson('/api/v1/admin/freelancers/verification-queue')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('rejects listing the verification queue without authentication', function () {
    $this->getJson('/api/v1/admin/freelancers/verification-queue')->assertUnauthorized();
});

it('denies listing the verification queue to a non-admin role', function () {
    $freelancer = User::factory()->freelancer()->create();
    $freelancer->assignRole(RoleName::Freelancer->value);

    $this->withHeaders(authHeader($freelancer))
        ->getJson('/api/v1/admin/freelancers/verification-queue')
        ->assertForbidden();
});
