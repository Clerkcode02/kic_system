<?php

declare(strict_types=1);

use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

class DummyDatabaseNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => 'test'];
    }
}

function unreadNotificationFor(User $user): DatabaseNotification
{
    $user->notify(new DummyDatabaseNotification());

    return $user->notifications()->first();
}

it('marks an unread notification as read for its owner', function () {
    $user = User::factory()->customer()->create();
    $user->assignRole(\App\Domain\User\Enums\RoleName::Customer->value);
    $notification = unreadNotificationFor($user);

    expect($notification->read_at)->toBeNull();

    $this->withHeaders(authHeader($user))
        ->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('rejects an unauthenticated read request', function () {
    $user = User::factory()->customer()->create();
    $notification = unreadNotificationFor($user);

    $this->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertUnauthorized();
});

it('denies a stranger from marking someone else\'s notification as read', function () {
    $owner = User::factory()->customer()->create();
    $owner->assignRole(\App\Domain\User\Enums\RoleName::Customer->value);
    $stranger = User::factory()->customer()->create();
    $stranger->assignRole(\App\Domain\User\Enums\RoleName::Customer->value);
    $notification = unreadNotificationFor($owner);

    $this->withHeaders(authHeader($stranger))
        ->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertForbidden();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('returns 404 for a nonexistent notification id', function () {
    $user = User::factory()->customer()->create();
    $user->assignRole(\App\Domain\User\Enums\RoleName::Customer->value);

    $this->withHeaders(authHeader($user))
        ->postJson('/api/v1/notifications/'.Str::uuid()->toString().'/read')
        ->assertNotFound();
});
