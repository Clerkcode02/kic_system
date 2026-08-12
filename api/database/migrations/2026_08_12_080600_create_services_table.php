<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\ServicePricingType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained();
            $table->string('title');
            $table->text('description');
            $table->string('pricing_type')->default(ServicePricingType::Fixed->value);
            $table->decimal('base_price', 12, 2);
            $table->char('currency', 3)->default('CAD');
            $table->integer('estimated_duration_minutes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('CREATE INDEX idx_services_category_active ON services (category_id) WHERE is_active = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
