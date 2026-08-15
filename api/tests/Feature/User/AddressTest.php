<?php

declare(strict_types=1);

use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\Address;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function addressCustomer(): User
{
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);

    return $user;
}

it('lists only the authenticated user\'s addresses, most recent default first', function () {
    $customer = addressCustomer();
    $other = addressCustomer();

    Address::factory()->for($other, 'user')->create();
    $older = Address::factory()->for($customer, 'user')->create(['is_default' => false, 'created_at' => now()->subDay()]);
    $default = Address::factory()->for($customer, 'user')->create(['is_default' => true]);

    $response = $this->actingAs($customer)->getJson('/api/v1/me/addresses')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toHaveCount(2)
        ->and($ids->first())->toBe($default->id)
        ->and($ids->last())->toBe($older->id);
});

it('rejects an unauthenticated address list request', function () {
    $this->getJson('/api/v1/me/addresses')->assertUnauthorized();
});

it('creates an address for the authenticated user and marks the first one default', function () {
    $customer = addressCustomer();

    $this->actingAs($customer)
        ->postJson('/api/v1/me/addresses', [
            'label' => 'Home',
            'street' => '123 Main St',
            'city' => 'Toronto',
            'state_province' => 'ON',
            'postal_code' => 'M5V 2T6',
            'lat' => 43.6532,
            'lng' => -79.3832,
        ])
        ->assertCreated()
        ->assertJsonPath('data.label', 'Home')
        ->assertJsonPath('data.is_default', true);

    expect(Address::query()->where('user_id', $customer->id)->count())->toBe(1);
});

it('demotes the previous default when a new address is saved as default', function () {
    $customer = addressCustomer();
    $existing = Address::factory()->for($customer, 'user')->create(['is_default' => true]);

    $this->actingAs($customer)
        ->postJson('/api/v1/me/addresses', [
            'street' => '456 Queen St',
            'city' => 'Toronto',
            'state_province' => 'ON',
            'postal_code' => 'M5V 2A1',
            'lat' => 43.65,
            'lng' => -79.38,
            'is_default' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_default', true);

    expect($existing->fresh()->is_default)->toBeFalse();
});

it('rejects an unauthenticated address creation request', function () {
    $this->postJson('/api/v1/me/addresses', [])->assertUnauthorized();
});

it('rejects an address with an invalid province', function () {
    $customer = addressCustomer();

    $this->actingAs($customer)
        ->postJson('/api/v1/me/addresses', [
            'street' => '123 Main St',
            'city' => 'Toronto',
            'state_province' => 'ZZ',
            'postal_code' => 'M5V 2T6',
            'lat' => 43.6532,
            'lng' => -79.3832,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['state_province']);
});
