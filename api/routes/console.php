<?php

declare(strict_types=1);

use App\Domain\Booking\Jobs\ExpireUnquotedBookingsJob;
use App\Domain\Booking\Jobs\NudgeUnquotedProviderJob;
use App\Domain\Payment\Jobs\RunProviderPayoutJob;
use App\Domain\Quotation\Jobs\ExpireStaleQuotationsJob;
use App\Domain\Quotation\Jobs\SendQuotationExpiryReminderJob;
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
