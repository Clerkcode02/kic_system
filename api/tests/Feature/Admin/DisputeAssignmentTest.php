<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

it('lets an admin assign an open dispute to another admin and audits it', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);
    $token = $admin->createToken('device')->plainTextToken;

    $assignee = User::factory()->admin()->create();
    $assignee->assignRole(RoleName::Admin->value);

    $dispute = Dispute::factory()->open()->create();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/admin/disputes/{$dispute->id}/assign", ['admin_id' => $assignee->id])
        ->assertOk()
        ->assertJsonPath('data.assigned_admin_id', $assignee->id);

    expect(AuditLog::query()
        ->where('action', 'dispute.assigned')
        ->where('actor_id', $admin->id)
        ->exists())->toBeTrue();
});

it('rejects assigning a resolved dispute', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);
    $token = $admin->createToken('device')->plainTextToken;

    $assignee = User::factory()->admin()->create();
    $assignee->assignRole(RoleName::Admin->value);

    $dispute = Dispute::factory()->resolved()->create();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/admin/disputes/{$dispute->id}/assign", ['admin_id' => $assignee->id])
        ->assertStatus(409);
});

it('rejects assignment to a non-admin user', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);
    $token = $admin->createToken('device')->plainTextToken;

    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);

    $dispute = Dispute::factory()->open()->create();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/admin/disputes/{$dispute->id}/assign", ['admin_id' => $customer->id])
        ->assertStatus(422);
});

it('denies a non-admin from assigning disputes', function () {
    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);
    $token = $customer->createToken('device')->plainTextToken;

    $dispute = Dispute::factory()->open()->create();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/admin/disputes/{$dispute->id}/assign", ['admin_id' => $customer->id])
        ->assertForbidden();
});
