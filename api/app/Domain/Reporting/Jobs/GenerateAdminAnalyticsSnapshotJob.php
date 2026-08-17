<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Jobs;

use App\Domain\Reporting\Models\AdminAnalyticsSnapshot;
use App\Domain\Reporting\Queries\ComputeAdminAnalyticsMetricsQuery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * CLAUDE.md §8: hourly snapshot for GET /admin/dashboard/metrics — the
 * dashboard reads this table, never live-aggregates (SRS §12).
 */
final class GenerateAdminAnalyticsSnapshotJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('reporting');
    }

    public function handle(ComputeAdminAnalyticsMetricsQuery $query): void
    {
        AdminAnalyticsSnapshot::create([
            'snapshot_at' => now(),
            'metrics' => $query->handle(),
        ]);
    }
}
