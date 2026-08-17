<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Reporting;

use App\Domain\Reporting\Models\AdminAnalyticsSnapshot;
use App\Domain\User\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminAnalyticsSnapshotResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardMetricsController extends Controller
{
    private const HISTORY_LIMIT = 30;

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can(PermissionName::AnalyticsView->value), 403);

        // SRS §18: analytics reads never hit the primary write connection.
        $snapshots = AdminAnalyticsSnapshot::on('pgsql_read')
            ->orderByDesc('snapshot_at')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values();

        return AdminAnalyticsSnapshotResource::collection($snapshots);
    }
}
