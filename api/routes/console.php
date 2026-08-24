<?php

declare(strict_types=1);

use App\Domain\Booking\Jobs\ExpireUnquotedBookingsJob;
use App\Domain\Booking\Jobs\NudgeUnquotedProviderJob;
use App\Domain\Payment\Jobs\RunProviderPayoutJob;
use App\Domain\Quotation\Jobs\ExpireStaleQuotationsJob;
use App\Domain\Quotation\Jobs\SendQuotationExpiryReminderJob;
use App\Domain\Reporting\Jobs\GenerateAdminAnalyticsSnapshotJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SRS §14/§15 — every scheduled job runs ->withoutOverlapping()->onOneServer()
// since the app runs on multiple app servers behind the load balancer.
Schedule::job(new ExpireStaleQuotationsJob())->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::job(new ExpireUnquotedBookingsJob())->everyFifteenMinutes()->withoutOverlapping()->onOneServer();

// Not in the §14 job table (which only lists the two expiry sweeps) —
// added per §9's reminder cadence ("T-24h/T-2h... provider reminded daily
// if no quotation sent within 48h").
Schedule::job(new SendQuotationExpiryReminderJob())->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::job(new NudgeUnquotedProviderJob())->dailyAt('09:00')->withoutOverlapping()->onOneServer();

// CLAUDE.md §8 — nightly provider payout ledger sweep.
Schedule::job(new RunProviderPayoutJob())->dailyAt('02:00')->withoutOverlapping()->onOneServer();

// SRS §12 — admin analytics dashboard reads from this hourly snapshot,
// never live aggregation.
Schedule::job(new GenerateAdminAnalyticsSnapshotJob())->hourly()->withoutOverlapping()->onOneServer();

// SRS §21 — application-level backup (app code + a `pg_dump` of the
// database, per config/backup.php), shipped to the dedicated 'backups' S3
// disk. This is a second line of defense, not the primary recovery path —
// the managed Postgres instance's own PITR (SRS §21 infra checklist) is
// what backs point-in-time restores; this is what you'd reach for to
// restore into a fresh environment or recover from a bucket/account-level
// disaster. `backup:clean` enforces the retention policy in
// config/backup.php's 'cleanup' block; `backup:monitor` fires
// UnhealthyBackupWasFoundNotification (config/backup.php's mail
// notification) if a backup goes stale or oversized.
Schedule::command('backup:run')->dailyAt('01:00')->withoutOverlapping()->onOneServer();
Schedule::command('backup:clean')->dailyAt('01:30')->withoutOverlapping()->onOneServer();
Schedule::command('backup:monitor')->dailyAt('02:30')->withoutOverlapping()->onOneServer();
