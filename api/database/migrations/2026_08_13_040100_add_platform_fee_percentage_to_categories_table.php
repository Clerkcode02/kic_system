<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Null = fall back to platform_settings['platform.default_fee_percentage']
            // (CLAUDE.md §12 admin surface — "platform fee config: global
            // default + per-category overrides").
            $table->decimal('platform_fee_percentage', 5, 2)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('platform_fee_percentage');
        });
    }
};
