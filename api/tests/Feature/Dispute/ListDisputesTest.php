<?php

declare(strict_types=1);

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

it('lists only disputes the customer raised or is a party to via GET /api/v1/disputes', function () {
    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);

    $provider = User::factory()->provider()->create();
    $provider->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->verified()->create(['user_id' => $provider->id]);

    $ownBooking = Booking::factory()->create(['customer_id' => $customer->id, 'provider_id' => $business->id]);
    $ownDispute = Dispute::factory()->create([
        'disputable_type' => 'booking',
        'disputable_id' => $ownBooking->id,
        'raised_by' => $customer->id,
    ]);

    $strangerBooking = Booking::factory()->create(['provider_id' => $business->id]);
    Dispute::factory()->create([
        'disputable_type' => 'booking',
        'disputable_id' => $strangerBooking->id,
        'raised_by' => $strangerBooking->customer_id,
    ]);

    $this->withHeaders(authHeader($customer))
        ->getJson('/api/v1/disputes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownDispute->id);
});

it('lets an admin with disputes.view see every dispute regardless of party', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);

    Dispute::factory()->count(3)->create();

    $this->withHeaders(authHeader($admin))
        ->getJson('/api/v1/disputes')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('returns an empty list when the user has no disputes', function () {
    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);

    $this->withHeaders(authHeader($customer))
        ->getJson('/api/v1/disputes')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('rejects listing disputes without authentication', function () {
    $this->getJson('/api/v1/disputes')->assertUnauthorized();
});

it('denies listing disputes to a user with no role or permissions', function () {
    $rogue = User::factory()->customer()->create();

    $this->withHeaders(authHeader($rogue))
        ->getJson('/api/v1/disputes')
        ->assertForbidden();
});
