<?php

declare(strict_types=1);

use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('payable_type');
            $table->uuid('payable_id');
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->decimal('platform_fee_amount', 12, 2);
            $table->decimal('provider_net_amount', 12, 2);
            $table->char('currency', 3)->default('CAD');
            $table->string('type')->default(PaymentType::Full->value);
            $table->string('status')->default(PaymentStatus::Pending->value);
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
