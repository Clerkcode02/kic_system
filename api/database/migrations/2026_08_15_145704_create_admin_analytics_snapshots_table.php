<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('admin_analytics_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestampTz('snapshot_at');
            $table->jsonb('metrics');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('snapshot_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_analytics_snapshots');
    }
};
