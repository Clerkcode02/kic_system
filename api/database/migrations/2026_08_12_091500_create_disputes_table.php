<?php

declare(strict_types=1);

use App\Domain\Dispute\Enums\DisputeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('disputable_type');
            $table->uuid('disputable_id');
            $table->foreignUuid('raised_by')->constrained('users');
            $table->string('status')->default(DisputeStatus::Open->value);
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['disputable_type', 'disputable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
