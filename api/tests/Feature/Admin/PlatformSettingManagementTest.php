<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use App\Support\Facades\Settings;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

it('lets an admin list and update platform settings, caches the read, and audits the change', function () {
    PlatformSetting::factory()->create([
        'key' => 'quotation.default_validity_hours',
        'value' => '120',
        'type' => 'integer',
    ]);

    expect(Settings::get('quotation.default_validity_hours'))->toBe(120);

    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);
    $token = $admin->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/platform-settings')
        ->assertOk()
        ->assertJsonFragment(['key' => 'quotation.default_validity_hours']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson('/api/v1/admin/platform-settings/quotation.default_validity_hours', [
            'value' => '96',
        ])
        ->assertOk()
        ->assertJsonPath('data.value', 96);

    // Cache was invalidated by the write — a fresh read reflects it
    // immediately rather than the stale value for up to an hour.
    expect(Settings::get('quotation.default_validity_hours'))->toBe(96);

    expect(AuditLog::query()
        ->where('action', 'platform_setting.updated')
        ->where('actor_id', $admin->id)
        ->exists())->toBeTrue();
});

it('denies a non-admin from managing platform settings', function () {
    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);
    $token = $customer->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/platform-settings')
        ->assertForbidden();
});
