<?php

declare(strict_types=1);

use App\Domain\Payment\Enums\PayoutStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('businesses');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('CAD');
            $table->string('stripe_transfer_id')->nullable();
            $table->string('status')->default(PayoutStatus::Scheduled->value);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
