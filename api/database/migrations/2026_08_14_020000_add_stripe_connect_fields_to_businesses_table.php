<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('stripe_connect_account_id')->nullable()->unique()->after('user_id');
            $table->boolean('stripe_charges_enabled')->default(false)->after('stripe_connect_account_id');
            $table->boolean('stripe_payouts_enabled')->default(false)->after('stripe_charges_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['stripe_connect_account_id', 'stripe_charges_enabled', 'stripe_payouts_enabled']);
        });
    }
};
