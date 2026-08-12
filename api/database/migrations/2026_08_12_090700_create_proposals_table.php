<?php

declare(strict_types=1);

use App\Domain\Freelance\Enums\ProposalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('freelancer_id')->constrained('freelancer_profiles');
            $table->decimal('proposed_amount', 12, 2);
            $table->char('currency', 3)->default('CAD');
            $table->text('cover_letter');
            $table->integer('delivery_days');
            $table->string('status')->default(ProposalStatus::Submitted->value);
            $table->timestamps();

            // One proposal per freelancer per project.
            $table->unique(['project_id', 'freelancer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
