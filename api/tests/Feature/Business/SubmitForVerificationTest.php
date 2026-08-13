<?php

declare(strict_types=1);

use App\Domain\Business\Models\Business;
use App\Domain\Business\Notifications\BusinessSubmittedForVerificationNotification;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->seed(RoleAndPermissionSeeder::class);
});

it('submits a pending business for verification and notifies admins', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::SuperAdmin->value);

    $user = User::factory()->provider()->create();
    $user->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->pending()->create(['user_id' => $user->id]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/provider/me/submit-for-verification')
        ->assertOk()
        ->assertJsonPath('data.verification_status', 'pending');

    Notification::assertSentTo($admin, BusinessSubmittedForVerificationNotification::class);
});

it('rejects re-submitting an already verified business', function () {
    $user = User::factory()->provider()->create();
    $user->assignRole(RoleName::ProviderOwner->value);
    Business::factory()->verified()->create(['user_id' => $user->id]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/provider/me/submit-for-verification')
        ->assertStatus(409)
        ->assertJsonPath('code', 'business_already_verified');
});

it('denies a customer from submitting for verification', function () {
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/provider/me/submit-for-verification')
        ->assertForbidden();
});

it('rejects an unauthenticated request', function () {
    $this->postJson('/api/v1/provider/me/submit-for-verification')->assertUnauthorized();
});
