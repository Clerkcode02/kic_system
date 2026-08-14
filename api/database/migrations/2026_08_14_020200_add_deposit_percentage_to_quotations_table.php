<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Nullable — most quotations are paid in full; a provider opts
            // into a deposit by setting this (CLAUDE.md §5 "Deposits").
            $table->decimal('deposit_percentage', 5, 2)->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('deposit_percentage');
        });
    }
};
