<?php

declare(strict_types=1);

use App\Domain\Freelance\Enums\FreelancerApprovalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('freelancer_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline');
            $table->text('bio');
            $table->decimal('hourly_rate', 12, 2);
            $table->char('currency', 3)->default('CAD');
            $table->integer('years_experience')->default(0);
            $table->string('approval_status')->default(FreelancerApprovalStatus::Pending->value);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freelancer_profiles');
    }
};
