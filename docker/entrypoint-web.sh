#!/bin/sh
set -e

# Deliberately no `php artisan migrate` here — migrations run once, behind
# the manual approval gate in deploy.yml, before any new revision of this
# entrypoint is rolled out. Running them per-replica-start would race
# multiple app servers against the same migration.
cd /var/www/api

# `config:cache` is deliberately skipped: config/scramble.php's
# `security_strategy` holds a live Dedoc\Scramble\Support\Generator\
# SecurityScheme instance (required by the package's own documented
# pattern), which var_export can't serialize, so config:cache hard-fails
# the whole boot. That value is only read by `php artisan
# app:export-openapi` (CI/dev tooling), never on the request path, so
# leaving config uncached costs a bit of boot-time config parsing and
# nothing else.
php artisan route:cache
php artisan event:cache

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
