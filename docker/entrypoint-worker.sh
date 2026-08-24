#!/bin/sh
set -e

# CLAUDE.md §21 "separate Horizon worker deployment scaled independently
# from web traffic" — same image as entrypoint-web.sh, different process.
# No nginx/php-fpm here: this container only drains queues.
cd /var/www/api
# See entrypoint-web.sh — config:cache is skipped repo-wide because
# config/scramble.php holds a non-serializable object.

exec php artisan horizon
