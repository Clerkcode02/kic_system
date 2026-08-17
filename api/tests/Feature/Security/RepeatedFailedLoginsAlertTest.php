<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use App\Domain\User\Notifications\RepeatedFailedLoginsNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->seed(RoleAndPermissionSeeder::class);
});

/**
 * SRS §17: "anomaly alerts on repeated failed logins." End-to-end through
 * the real POST /api/v1/auth/login endpoint — LoginUser -> FailedLoginMonitor
 * -> RepeatedFailedLoginsDetected -> NotifyAdminsOfRepeatedFailedLogins ->
 * RecordAuditEntry (both fire off the same Auditable event).
 */
it('notifies every admin and audits the event once 5 bad-credential logins hit the same email in the window', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);

    $superAdmin = User::factory()->admin()->create();
    $superAdmin->assignRole(RoleName::SuperAdmin->value);

    $target = User::factory()->create(['email' => 'victim@example.com']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'victim@example.com',
            'password' => 'definitely-wrong-password',
        ])->assertStatus(422);
    }

    $adminNotifications = DatabaseNotification::query()
        ->where('notifiable_id', $admin->id)
        ->where('type', RepeatedFailedLoginsNotification::class)
        ->count();

    $superAdminNotifications = DatabaseNotification::query()
        ->where('notifiable_id', $superAdmin->id)
        ->where('type', RepeatedFailedLoginsNotification::class)
        ->count();

    expect($adminNotifications)->toBe(1);
    expect($superAdminNotifications)->toBe(1);

    // No notification for a user that isn't the login target.
    $targetNotifications = DatabaseNotification::query()
        ->where('notifiable_id', $target->id)
        ->where('type', RepeatedFailedLoginsNotification::class)
        ->count();
    expect($targetNotifications)->toBe(0);

    expect(AuditLog::query()->where('action', 'user.repeated_failed_logins_detected')->count())->toBe(1);
});

it('does not notify admins when failed logins stay under the threshold', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);

    for ($i = 0; $i < 4; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'victim@example.com',
            'password' => 'definitely-wrong-password',
        ])->assertStatus(422);
    }

    expect(DatabaseNotification::query()->where('type', RepeatedFailedLoginsNotification::class)->count())->toBe(0);
    expect(AuditLog::query()->where('action', 'user.repeated_failed_logins_detected')->count())->toBe(0);
});
