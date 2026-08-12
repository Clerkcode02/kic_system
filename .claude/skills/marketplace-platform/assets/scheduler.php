<?php

// Scheduled tasks — SRS §15
// All scheduled jobs use ->withoutOverlapping() and ->onOneServer() (cache-based lock)
// since the app runs on multiple app servers behind the load balancer.

Schedule::job(new ExpireStaleQuotationsJob)->everyFiveMinutes();
Schedule::job(new ExpireUnquotedBookingsJob)->everyFifteenMinutes();
Schedule::job(new SendBookingReminderJob)->hourly();
Schedule::job(new RunProviderPayoutJob)->dailyAt('02:00');
Schedule::job(new GenerateAdminAnalyticsSnapshotJob)->hourly();
Schedule::command('audit:archive-old-logs')->monthly();
Schedule::command('backup:run')->dailyAt('01:00');
