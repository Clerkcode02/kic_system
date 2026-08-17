<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->foreignUuid('assigned_admin_id')->nullable()->after('raised_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_admin_id');
        });
    }
};
