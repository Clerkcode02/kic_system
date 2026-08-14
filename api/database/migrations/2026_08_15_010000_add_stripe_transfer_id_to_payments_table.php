<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Set once ReleaseMilestoneEscrow's Stripe Transfer succeeds —
            // doubles as the idempotency guard against creating a second
            // transfer for the same milestone escrow payment.
            $table->string('stripe_transfer_id')->nullable()->unique()->after('stripe_payment_intent_id');

            // Links a succeeded booking Payment to the nightly Payout batch
            // that swept it, so RunProviderPayoutJob never double-counts a
            // payment across two nightly runs.
            $table->foreignUuid('payout_id')->nullable()->after('payable_id')->constrained('payouts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_id');
            $table->dropColumn('stripe_transfer_id');
        });
    }
};
