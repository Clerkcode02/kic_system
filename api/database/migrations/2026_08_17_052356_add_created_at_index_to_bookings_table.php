<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SRS §18 performance verification: ComputeAdminAnalyticsMetricsQuery's
 * `bookings_active_24h` metric filters purely on `created_at`, which had no
 * supporting index — a sequential scan at any real booking volume.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::statement('CREATE INDEX idx_bookings_created_at ON bookings (created_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_bookings_created_at');
    }
};
