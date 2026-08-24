#!/bin/sh
set -e

# One long-running replica evaluates routes/console.php's Schedule::
# entries every minute — `->onOneServer()` on each of them (CLAUDE.md §8)
# is what makes it safe to still run more than one of these if ever
# needed. `schedule:work` is Laravel's built-in foreground loop, built for
# exactly this container-without-cron case.
cd /var/www/api
# See entrypoint-web.sh — config:cache is skipped repo-wide because
# config/scramble.php holds a non-serializable object.

exec php artisan schedule:work
