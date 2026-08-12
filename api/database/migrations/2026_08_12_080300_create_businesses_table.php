<?php

declare(strict_types=1);

use App\Domain\Business\Enums\BusinessVerificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('legal_name');
            $table->string('registration_number')->unique();
            $table->string('verification_status')->default(BusinessVerificationStatus::Pending->value);
            $table->jsonb('business_hours');
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->integer('max_bookings_per_day');
            $table->timestamps();
        });

        // Laravel has no geography() blueprint macro without a spatial package,
        // so the column and its index are added via raw SQL per project convention.
        DB::statement('ALTER TABLE businesses ADD COLUMN location geography(Point, 4326)');
        DB::statement('CREATE INDEX idx_services_geo ON businesses USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
