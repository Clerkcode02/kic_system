<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Business\Models\Business;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);
    $this->adminToken = $admin->createToken('device')->plainTextToken;
});

it('lists pending businesses in the verification queue', function () {
    Business::factory()->pending()->count(2)->create();
    Business::factory()->verified()->create();

    $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->getJson('/api/v1/admin/businesses/verification-queue')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lets an admin approve a pending business and audits it', function () {
    $business = Business::factory()->pending()->create();

    $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson("/api/v1/admin/businesses/{$business->id}/verification/approve")
        ->assertOk()
        ->assertJsonPath('data.verification_status', 'verified');

    expect(AuditLog::query()->where('auditable_id', $business->id)->where('action', 'like', '%approved%')->exists())
        ->toBeTrue();
});

it('requires a reason to reject a pending business', function () {
    $business = Business::factory()->pending()->create();

    $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson("/api/v1/admin/businesses/{$business->id}/verification/reject", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson("/api/v1/admin/businesses/{$business->id}/verification/reject", [
            'reason' => 'Business license document is expired.',
        ])
        ->assertOk()
        ->assertJsonPath('data.verification_status', 'rejected');
});

it('bulk-approves several pending businesses and reports per-item outcomes', function () {
    $businesses = Business::factory()->pending()->count(3)->create();
    $alreadyVerified = Business::factory()->verified()->create();

    $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson('/api/v1/admin/businesses/verification/bulk-approve', [
            'ids' => [...$businesses->pluck('id'), $alreadyVerified->id],
        ])
        ->assertOk()
        ->assertJsonCount(3, 'data.succeeded')
        ->assertJsonCount(1, 'data.failed');
});

it('denies a non-admin from viewing the verification queue', function () {
    $provider = User::factory()->provider()->create();
    $provider->assignRole(RoleName::ProviderOwner->value);
    $token = $provider->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/businesses/verification-queue')
        ->assertForbidden();
});
