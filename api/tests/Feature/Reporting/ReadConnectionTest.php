<?php

declare(strict_types=1);

use App\Domain\Reporting\Models\AdminAnalyticsSnapshot;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * SRS §18: "Configure a read-connection for reporting queries (same DB
 * now, swappable to a replica)." config/database.php's `pgsql_read`
 * connection falls back to every DB_* var when its DB_READ_* counterpart
 * is unset, so this doesn't assert the config points anywhere different —
 * only that the connection exists, is reachable, and that the analytics
 * dashboard endpoint actually reads through it rather than the default
 * connection.
 */
it('has a pgsql_read connection configured and reachable', function () {
    expect(config('database.connections.pgsql_read'))->not->toBeNull()
        ->and(config('database.connections.pgsql_read.driver'))->toBe('pgsql');

    // Round-trips a real query — proves the connection is usable, not just
    // present in config.
    expect(DB::connection('pgsql_read')->select('select 1 as ok'))->toEqual([(object) ['ok' => 1]]);
});

it('serves the admin analytics dashboard through the read connection', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);
    $token = $admin->createToken('test')->plainTextToken;

    AdminAnalyticsSnapshot::factory()->create();

    $queriesOnReadConnection = 0;

    DB::connection('pgsql_read')->listen(function ($query) use (&$queriesOnReadConnection) {
        if (str_contains($query->sql, 'admin_analytics_snapshots')) {
            $queriesOnReadConnection++;
        }
    });

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/dashboard/metrics')
        ->assertOk();

    expect($queriesOnReadConnection)->toBeGreaterThan(0);
});
