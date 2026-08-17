<?php

declare(strict_types=1);

use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Payout;
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

it('lists recent payouts for an admin', function () {
    Payout::factory()->paid()->count(3)->create();

    $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->getJson('/api/v1/admin/payouts')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('lists failed milestone transfer payments', function () {
    Payment::factory()->escrow()->failed()->create(['stripe_transfer_id' => 'tr_failed_1']);
    Payment::factory()->escrow()->succeeded()->create();

    $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->getJson('/api/v1/admin/payouts/failed-transfers')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('rejects retrying a payment whose transfer did not fail', function () {
    $payment = Payment::factory()->escrow()->succeeded()->create();

    $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson("/api/v1/admin/payouts/failed-transfers/{$payment->id}/retry")
        ->assertStatus(409);
});

it('denies a non-admin from viewing payouts', function () {
    $provider = User::factory()->provider()->create();
    $provider->assignRole(RoleName::ProviderOwner->value);
    $token = $provider->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/payouts')
        ->assertForbidden();
});
