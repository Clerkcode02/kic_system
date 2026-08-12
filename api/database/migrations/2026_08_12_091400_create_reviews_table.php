<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reviewer_id')->constrained('users');
            $table->foreignUuid('reviewee_id')->constrained('users');
            $table->string('reviewable_type');
            $table->uuid('reviewable_id');
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->text('provider_reply')->nullable();
            $table->timestamp('edit_locked_at')->nullable();
            $table->timestamps();

            $table->index(['reviewable_type', 'reviewable_id']);
        });

        DB::statement('CREATE INDEX idx_reviews_reviewee ON reviews (reviewee_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
