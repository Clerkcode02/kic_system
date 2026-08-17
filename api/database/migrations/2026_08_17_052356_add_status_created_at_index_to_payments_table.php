<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SRS §18 performance verification: ComputeAdminAnalyticsMetricsQuery's
 * `gmv_24h` and `payout_volume_24h` metrics both filter on
 * `status = succeeded AND created_at >= :since`, which had no supporting
 * index — a sequential scan at any real payment volume.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::statement('CREATE INDEX idx_payments_status_created_at ON payments (status, created_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_payments_status_created_at');
    }
};
