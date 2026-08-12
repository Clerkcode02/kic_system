<?php

declare(strict_types=1);

use App\Domain\Freelance\Enums\ContractStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->unique()->constrained();
            $table->foreignUuid('proposal_id')->constrained();
            $table->decimal('total_amount', 12, 2);
            $table->char('currency', 3)->default('CAD');
            $table->string('status')->default(ContractStatus::Active->value);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
