<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS §8 draws a single `InProgress --> Completed` edge triggered by
 * "provider marks complete + customer confirms", but the API exposes that
 * as two separate calls. `provider_completed_at` records the provider's
 * half so ConfirmCompletion can require it before firing the one status
 * transition the state machine actually knows about.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('provider_completed_at')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('provider_completed_at');
        });
    }
};
