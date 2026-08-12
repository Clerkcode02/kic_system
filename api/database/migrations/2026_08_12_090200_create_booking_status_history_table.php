<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('booking_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement(<<<'SQL'
            CREATE TRIGGER booking_status_history_no_update_delete
              BEFORE UPDATE OR DELETE ON booking_status_history
              FOR EACH ROW EXECUTE FUNCTION prevent_update_delete();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_history');
    }
};
