<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SRS §17 "Audit + alerting: anomaly alerts on repeated failed logins".
 * Append-only log of bad-credential login attempts (LoginUser::handle),
 * queried by App\Domain\User\Services\FailedLoginMonitor to detect and
 * debounce repeated-failed-login alerts. No `updated_at` — this table is
 * never mutated, only appended to and read from.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('failed_login_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Matches the lookup FailedLoginMonitor runs on every failed
        // attempt: "how many failures for this email in the last N minutes".
        DB::statement('CREATE INDEX idx_failed_login_attempts_email_date ON failed_login_attempts (email, created_at)');

        // Reuses prevent_update_delete(), defined by the audit_logs
        // migration — same append-only guarantee, same enforcement mechanism.
        DB::statement(<<<'SQL'
            CREATE TRIGGER failed_login_attempts_no_update_delete
              BEFORE UPDATE OR DELETE ON failed_login_attempts
              FOR EACH ROW EXECUTE FUNCTION prevent_update_delete();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_login_attempts');
    }
};
