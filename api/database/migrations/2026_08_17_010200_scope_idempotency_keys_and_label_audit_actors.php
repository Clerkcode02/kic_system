<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two small changes that guest booking (SRS §6.1) requires of shared tables:
 *
 * 1. `idempotency_keys.key` was globally unique, which silently made one
 *    guest's key collide with another's. Keys are now unique per (key,
 *    scope), where scope is the acting identity — a user id for an
 *    authenticated caller, the hashed normalized email for a guest.
 * 2. `audit_logs.actor_id` is a users FK, so a guest actor has nowhere to
 *    land. `actor_label` carries `guest:<sha256 of normalized email>` while
 *    actor_id stays null — a raw guest email never reaches the trail.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->string('scope')->default('')->after('key');
        });

        DB::statement('ALTER TABLE idempotency_keys DROP CONSTRAINT IF EXISTS idempotency_keys_key_unique');
        DB::statement('CREATE UNIQUE INDEX idempotency_keys_key_scope_unique ON idempotency_keys (key, scope)');

        // audit_logs is append-only and carries a BEFORE UPDATE OR DELETE
        // trigger; adding a nullable column is DDL, not row DML, so the
        // trigger does not fire and no backfill is needed.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('actor_label')->nullable()->after('actor_id');
        });

        // Guest Stripe Customers are reused per normalized email and stored
        // on the booking (SRS §6.1 "Payments").
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('actor_label');
        });

        DB::statement('DROP INDEX IF EXISTS idempotency_keys_key_scope_unique');

        // Restoring the global unique index requires the duplicates that
        // scoping made legal to be gone first.
        DB::statement(<<<'SQL'
            DELETE FROM idempotency_keys a
              USING idempotency_keys b
              WHERE a.key = b.key AND a.ctid > b.ctid
        SQL);
        DB::statement('ALTER TABLE idempotency_keys ADD CONSTRAINT idempotency_keys_key_unique UNIQUE (key)');

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
