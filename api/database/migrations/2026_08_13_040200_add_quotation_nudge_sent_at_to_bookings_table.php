<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Last time the provider was nudged about a booking sitting in
            // waiting_for_quotation with no quote sent (SRS §9 — "provider
            // reminded daily if no quotation sent within 48h").
            $table->timestamp('quotation_nudge_sent_at')->nullable()->after('provider_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('quotation_nudge_sent_at');
        });
    }
};
