<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SRS §6.1 / CLAUDE.md §5 "Guest booking": a booking has exactly one actor —
 * `customer_id` OR a guest contact triple — and carries a denormalized
 * service-address snapshot so a guest needs no `addresses` row.
 *
 * The one-actor rule is enforced by a DB CHECK, not only by PHP: PHP-level
 * validation can be bypassed by a seeder, a console command, or a future
 * code path that forgets the rule; the constraint cannot.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('guest_name')->nullable()->after('customer_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_phone')->nullable()->after('guest_email');
            $table->string('guest_email_normalized')->nullable()->after('guest_phone');

            $table->foreignUuid('claimed_by_user_id')->nullable()->after('guest_email_normalized')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable()->after('claimed_by_user_id');

            // Denormalized snapshot of where the work happens. Always
            // populated — for registered users too — so every read path
            // (including the reduced guest resource) has one source for the
            // address regardless of actor kind.
            $table->string('service_address_line1')->nullable()->after('address_id');
            $table->string('service_address_line2')->nullable()->after('service_address_line1');
            $table->string('service_address_city')->nullable()->after('service_address_line2');
            $table->string('service_address_province')->nullable()->after('service_address_city');
            $table->string('service_address_postal_code')->nullable()->after('service_address_province');
        });

        // customer_id and address_id become nullable — a guest booking has
        // neither. Reversible: down() restores NOT NULL.
        DB::statement('ALTER TABLE bookings ALTER COLUMN customer_id DROP NOT NULL');
        DB::statement('ALTER TABLE bookings ALTER COLUMN address_id DROP NOT NULL');

        // Laravel has no geography() blueprint macro without a spatial
        // package — same raw-SQL approach as businesses.location.
        DB::statement('ALTER TABLE bookings ADD COLUMN service_location geography(Point, 4326)');
        DB::statement('CREATE INDEX idx_bookings_service_location ON bookings USING GIST (service_location)');

        // Partial: only guest bookings carry this, and it is only ever
        // queried for claiming and the per-email open-booking cap.
        DB::statement(<<<'SQL'
            CREATE INDEX idx_bookings_guest_email
              ON bookings (guest_email_normalized)
              WHERE guest_email_normalized IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_exactly_one_actor CHECK (
                (
                    customer_id IS NOT NULL
                    AND guest_name IS NULL
                    AND guest_email IS NULL
                    AND guest_phone IS NULL
                    AND guest_email_normalized IS NULL
                )
                OR
                (
                    customer_id IS NULL
                    AND guest_name IS NOT NULL
                    AND guest_email IS NOT NULL
                    AND guest_phone IS NOT NULL
                    AND guest_email_normalized IS NOT NULL
                )
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_exactly_one_actor');
        DB::statement('DROP INDEX IF EXISTS idx_bookings_guest_email');
        DB::statement('DROP INDEX IF EXISTS idx_bookings_service_location');
        DB::statement('ALTER TABLE bookings DROP COLUMN IF EXISTS service_location');

        // Guest rows have no customer_id/address_id, so they must go before
        // the columns can be NOT NULL again.
        DB::statement('DELETE FROM bookings WHERE customer_id IS NULL OR address_id IS NULL');
        DB::statement('ALTER TABLE bookings ALTER COLUMN customer_id SET NOT NULL');
        DB::statement('ALTER TABLE bookings ALTER COLUMN address_id SET NOT NULL');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('claimed_by_user_id');
            $table->dropColumn([
                'guest_name',
                'guest_email',
                'guest_phone',
                'guest_email_normalized',
                'claimed_at',
                'service_address_line1',
                'service_address_line2',
                'service_address_city',
                'service_address_province',
                'service_address_postal_code',
            ]);
        });
    }
};
