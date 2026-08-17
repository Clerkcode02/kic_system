<?php

declare(strict_types=1);

use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

it('fills in default preferences for every category on GET /api/v1/me/notification-preferences when none are saved', function () {
    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);

    $this->withHeaders(authHeader($customer))
        ->getJson('/api/v1/me/notification-preferences')
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonFragment(['category' => 'booking', 'in_app' => true, 'email' => true, 'push_web' => true])
        ->assertJsonFragment(['category' => 'verification', 'in_app' => true, 'email' => true, 'push_web' => true]);
});

it('reflects a saved preference alongside the defaults for untouched categories', function () {
    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);

    NotificationPreference::create([
        'user_id' => $customer->id,
        'category' => 'booking',
        'email' => false,
        'push_web' => true,
    ]);

    $this->withHeaders(authHeader($customer))
        ->getJson('/api/v1/me/notification-preferences')
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonFragment(['category' => 'booking', 'in_app' => true, 'email' => false, 'push_web' => true])
        ->assertJsonFragment(['category' => 'payment', 'in_app' => true, 'email' => true, 'push_web' => true]);
});

it('rejects fetching notification preferences without authentication', function () {
    $this->getJson('/api/v1/me/notification-preferences')->assertUnauthorized();
});

it('lets an authenticated user update their notification preferences via PATCH', function () {
    $freelancer = User::factory()->freelancer()->create();
    $freelancer->assignRole(RoleName::Freelancer->value);

    $this->withHeaders(authHeader($freelancer))
        ->patchJson('/api/v1/me/notification-preferences', [
            'preferences' => [
                ['category' => 'freelance', 'email' => false, 'push_web' => false],
            ],
        ])
        ->assertOk()
        ->assertJsonFragment(['category' => 'freelance', 'in_app' => true, 'email' => false, 'push_web' => false]);

    expect(NotificationPreference::query()
        ->where('user_id', $freelancer->id)
        ->where('category', 'freelance')
        ->first())
        ->email->toBeFalse()
        ->push_web->toBeFalse();
});

it('rejects updating notification preferences with an invalid category', function () {
    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);

    $this->withHeaders(authHeader($customer))
        ->patchJson('/api/v1/me/notification-preferences', [
            'preferences' => [
                ['category' => 'not-a-real-category', 'email' => false],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['preferences.0.category']);
});

it('rejects updating notification preferences without authentication', function () {
    $this->patchJson('/api/v1/me/notification-preferences', [
        'preferences' => [['category' => 'booking', 'email' => false]],
    ])->assertUnauthorized();
});
