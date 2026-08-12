<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingPaymentStatus;
use App\Domain\Booking\Enums\BookingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('booking_number')->unique();
            $table->foreignUuid('customer_id')->constrained('users');
            $table->foreignUuid('provider_id')->constrained('businesses');
            $table->foreignUuid('service_id')->constrained('services');
            $table->date('scheduled_date');
            $table->time('time_slot_start');
            $table->time('time_slot_end');
            $table->foreignUuid('address_id')->constrained('addresses');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->text('notes')->nullable();
            $table->string('status')->default(BookingStatus::Pending->value);
            $table->string('payment_status')->default(BookingPaymentStatus::Unpaid->value);
            $table->timestamps();
        });

        DB::statement('CREATE INDEX idx_bookings_provider_date ON bookings (provider_id, scheduled_date, time_slot_start)');
        DB::statement('CREATE INDEX idx_bookings_customer ON bookings (customer_id, status)');
        DB::statement("CREATE INDEX idx_bookings_status ON bookings (status) WHERE status NOT IN ('completed','cancelled')");
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
