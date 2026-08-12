<?php

declare(strict_types=1);

use App\Domain\Quotation\Enums\QuotationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();
            $table->decimal('labor_cost', 12, 2);
            $table->decimal('materials_cost', 12, 2);
            $table->decimal('additional_fees', 12, 2);
            $table->decimal('platform_fee', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->char('currency', 3)->default('CAD');
            $table->timestamp('valid_until');
            $table->integer('revision_number')->default(1);
            $table->string('status')->default(QuotationStatus::Sent->value);
            $table->timestamps();
        });

        DB::statement("CREATE INDEX idx_quotations_status_validuntil ON quotations (status, valid_until) WHERE status = 'sent'");
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
