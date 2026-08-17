<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SRS §6.1: guest authorization is a hashed, expiring, per-booking access
 * token presented as `X-Booking-Token`. Only the sha256 hash is stored —
 * the plaintext is returned exactly once at creation and emailed as a
 * tracking link, and is never persisted or logged (CLAUDE.md §2).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('booking_access_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();
            // sha256 hex — fixed 64 chars, unique so resolution is a single
            // equality lookup rather than a scan-and-compare.
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('created_ip')->nullable();
            $table->timestamps();
        });

        // Revoking every live token for a booking (on claim) and the
        // scheduled expiry sweep both filter to still-usable rows only.
        DB::statement(<<<'SQL'
            CREATE INDEX idx_booking_access_tokens_live
              ON booking_access_tokens (booking_id, expires_at)
              WHERE revoked_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_access_tokens');
    }
};
