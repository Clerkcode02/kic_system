<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('freelancer_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('skill_name');
            $table->string('proficiency_level')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freelancer_skills');
    }
};
