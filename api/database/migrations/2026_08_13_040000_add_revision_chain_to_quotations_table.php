<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Self-referencing chain so a revision can be traced back to the
            // row it superseded (CLAUDE.md Quotation §5 — revisions are
            // never edited in place, always a new row).
            $table->foreignUuid('previous_quotation_id')->nullable()->after('booking_id')
                ->constrained('quotations')->nullOnDelete();

            // Idempotency guards for the T-24h/T-2h pre-expiry reminder
            // sweep (SRS §9) — set once a reminder fires so a job retry or
            // an overlapping run never double-sends it.
            $table->timestamp('reminder_24h_sent_at')->nullable();
            $table->timestamp('reminder_2h_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_quotation_id');
            $table->dropColumn(['reminder_24h_sent_at', 'reminder_2h_sent_at']);
        });
    }
};
