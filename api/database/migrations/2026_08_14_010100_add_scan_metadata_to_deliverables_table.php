<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->foreignUuid('uploaded_by')->nullable()->after('milestone_id')->constrained('users')->nullOnDelete();
            $table->string('mime_type')->nullable()->after('file_path');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            $table->boolean('scanned')->default(false)->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by');
            $table->dropColumn(['mime_type', 'size_bytes', 'scanned']);
        });
    }
};
