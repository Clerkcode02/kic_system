<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Support\ValueObjects\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * SRS §18 performance verification: bulk-generates a realistically-shaped
 * dataset (businesses spread over the GTA, services, customers, bookings,
 * payments) at a scale where a missing index actually shows up in
 * EXPLAIN ANALYZE, without paying Eloquent-factory-per-row overhead —
 * chunked raw `insert()` calls instead. Not meant to be run against a
 * database anyone cares about; truncates the tables it touches first.
 *
 * Usage: php artisan perf:seed --bookings=50000
 */
class SeedPerformanceDataset extends Command
{
    protected $signature = 'perf:seed {--bookings=50000}';

    protected $description = 'Bulk-seed a large dataset for EXPLAIN ANALYZE performance verification (SRS §18)';

    private const CATEGORY_COUNT = 15;

    private const BUSINESS_COUNT = 25_000;

    private const SERVICES_PER_BUSINESS = 2;

    private const CUSTOMER_COUNT = 8_000;

    // Roughly the GTA bounding box, matching AddressFactory's spread.
    private const LAT_MIN = 43.60;

    private const LAT_MAX = 43.85;

    private const LNG_MIN = -79.60;

    private const LNG_MAX = -79.20;

    /**
     * Hashed once and reused for every seeded user — these accounts exist
     * only to satisfy FK constraints for query-plan verification, never to
     * actually authenticate, so paying bcrypt's cost per row (8,000+ calls
     * at ~doubled the raw insert time) buys nothing.
     */
    private string $sharedPasswordHash;

    public function handle(): int
    {
        // The query log otherwise accumulates every bound statement for
        // the life of the process — at 50k+ chunked inserts that alone
        // exhausts default memory long before the data does.
        DB::connection()->disableQueryLog();

        $this->sharedPasswordHash = Hash::make('password');

        $bookingCount = (int) $this->option('bookings');

        $this->warn('Truncating businesses/services/bookings/payments/perf users/addresses/categories...');
        $this->truncate();

        $this->info('Seeding categories...');
        $categoryIds = $this->seedCategories();

        $this->info('Seeding businesses (with PostGIS locations across the GTA)...');
        $businessIds = $this->seedBusinesses();

        $this->info('Seeding services...');
        $serviceIds = $this->seedServices($businessIds, $categoryIds);

        $this->info('Seeding provider availability (every business, every day)...');
        $this->seedAvailability($businessIds);

        $this->info('Seeding customers + addresses...');
        [$customerIds, $addressByCustomer] = $this->seedCustomers();

        $this->info("Seeding {$bookingCount} bookings...");
        $bookingIds = $this->seedBookings($bookingCount, $customerIds, $addressByCustomer, $businessIds, $serviceIds);

        $this->info('Seeding payments for ~60% of bookings...');
        $this->seedPayments($bookingIds);

        $this->info('Running ANALYZE so the planner has fresh statistics...');
        DB::statement('ANALYZE bookings, payments, businesses, services, categories, users, addresses');

        $this->info('Done.');
        $this->table(['Table', 'Rows'], [
            ['categories', count($categoryIds)],
            ['businesses', count($businessIds)],
            ['services', count($serviceIds)],
            ['users (customers)', count($customerIds)],
            ['bookings', count($bookingIds)],
            ['payments', DB::table('payments')->count()],
        ]);

        return self::SUCCESS;
    }

    private function truncate(): void
    {
        DB::statement('TRUNCATE TABLE payments, bookings, booking_status_history, booking_attachments, provider_availability, services, businesses RESTART IDENTITY CASCADE');
        DB::table('addresses')->delete();
        DB::table('users')->where('email', 'like', 'perf_%')->delete();
        DB::table('categories')->delete();
    }

    /**
     * @return list<string>
     */
    private function seedCategories(): array
    {
        $ids = [];
        $now = now();
        $rows = [];

        for ($i = 0; $i < self::CATEGORY_COUNT; $i++) {
            $id = Str::uuid7()->toString();
            $ids[] = $id;

            $rows[] = [
                'id' => $id,
                'parent_id' => null,
                'name' => "Perf Category {$i}",
                'slug' => "perf-category-{$i}",
                'icon' => null,
                'is_active' => true,
                'sort_order' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('categories')->insert($rows);

        return $ids;
    }

    private function randomLat(): float
    {
        return self::LAT_MIN + mt_rand() / mt_getrandmax() * (self::LAT_MAX - self::LAT_MIN);
    }

    private function randomLng(): float
    {
        return self::LNG_MIN + mt_rand() / mt_getrandmax() * (self::LNG_MAX - self::LNG_MIN);
    }

    /**
     * Businesses spread across all of Canada (not just the GTA) — with
     * every business clustered in the same small box as customer
     * addresses, a "near me" radius search matches ~100% of the table and
     * the planner correctly prefers a seq scan over the GIST index (small
     * table, no selectivity to exploit). ~90% nationwide, ~10% in the GTA,
     * so a Toronto-centered search is a genuinely selective minority.
     *
     * @return array{0: float, 1: float} [lat, lng]
     */
    private function randomBusinessLocation(): array
    {
        if (mt_rand(1, 100) <= 10) {
            return [$this->randomLat(), $this->randomLng()];
        }

        return [
            42.0 + mt_rand() / mt_getrandmax() * (60.0 - 42.0),
            -140.0 + mt_rand() / mt_getrandmax() * (-52.0 - -140.0),
        ];
    }

    /**
     * @return list<string>
     */
    private function seedBusinesses(): array
    {
        $ids = [];
        $now = now();
        $userRows = [];
        $businessSeeds = [];

        for ($i = 0; $i < self::BUSINESS_COUNT; $i++) {
            $userId = Str::uuid7()->toString();
            $businessId = Str::uuid7()->toString();
            $ids[] = $businessId;

            $userRows[] = [
                'id' => $userId,
                'name' => "Perf Provider {$i}",
                'email' => "perf_provider_{$i}@example.test",
                'phone' => '+141655500'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'password' => $this->sharedPasswordHash,
                'status' => 'active',
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            [$lat, $lng] = $this->randomBusinessLocation();

            $businessSeeds[] = [
                'id' => $businessId,
                'user_id' => $userId,
                'legal_name' => "Perf Business {$i} Inc.",
                'registration_number' => 'PERFBN'.$i,
                'verification_status' => BusinessVerificationStatus::Verified->value,
                'business_hours' => json_encode(['mon' => ['09:00', '17:00']]),
                'rating_avg' => round(mt_rand(30, 50) / 10, 2),
                'max_bookings_per_day' => mt_rand(5, 20),
                'created_at' => $now,
                'updated_at' => $now,
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        foreach (array_chunk($userRows, 1000) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        foreach (array_chunk($businessSeeds, 500) as $chunk) {
            $valueGroups = [];
            $bindings = [];

            foreach ($chunk as $row) {
                $valueGroups[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ST_MakePoint(?, ?)::geography)';
                $bindings[] = $row['id'];
                $bindings[] = $row['user_id'];
                $bindings[] = $row['legal_name'];
                $bindings[] = $row['registration_number'];
                $bindings[] = $row['verification_status'];
                $bindings[] = $row['business_hours'];
                $bindings[] = $row['rating_avg'];
                $bindings[] = $row['max_bookings_per_day'];
                $bindings[] = $row['created_at'];
                $bindings[] = $row['updated_at'];
                $bindings[] = $row['lng'];
                $bindings[] = $row['lat'];
            }

            $sql = 'INSERT INTO businesses '
                .'(id, user_id, legal_name, registration_number, verification_status, business_hours, rating_avg, max_bookings_per_day, created_at, updated_at, location) '
                .'VALUES '.implode(', ', $valueGroups);

            DB::statement($sql, $bindings);
        }

        return $ids;
    }

    /**
     * @param  list<string>  $businessIds
     * @param  list<string>  $categoryIds
     * @return list<string>
     */
    private function seedServices(array $businessIds, array $categoryIds): array
    {
        $ids = [];
        $now = now();
        $rows = [];

        foreach ($businessIds as $businessId) {
            for ($j = 0; $j < self::SERVICES_PER_BUSINESS; $j++) {
                $id = Str::uuid7()->toString();
                $ids[] = $id;

                $rows[] = [
                    'id' => $id,
                    'business_id' => $businessId,
                    'category_id' => $categoryIds[array_rand($categoryIds)],
                    'title' => 'Perf Service '.Str::random(8),
                    'description' => 'Seeded for performance verification.',
                    'pricing_type' => ServicePricingType::Fixed->value,
                    'base_price' => mt_rand(4_000, 40_000) / 100,
                    'currency' => 'CAD',
                    'estimated_duration_minutes' => 60,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('services')->insert($chunk);
        }

        return $ids;
    }

    /**
     * @param  list<string>  $businessIds
     */
    private function seedAvailability(array $businessIds): void
    {
        $now = now();
        $rows = [];

        foreach ($businessIds as $businessId) {
            foreach (range(0, 6) as $day) {
                $rows[] = [
                    'id' => Str::uuid7()->toString(),
                    'business_id' => $businessId,
                    'day_of_week' => $day,
                    'start_time' => '08:00:00',
                    'end_time' => '18:00:00',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 2000) as $chunk) {
            DB::table('provider_availability')->insert($chunk);
        }
    }

    /**
     * @return array{0: list<string>, 1: array<string, string>}
     */
    private function seedCustomers(): array
    {
        $ids = [];
        $addressByCustomer = [];
        $now = now();
        $userRows = [];
        $addressRows = [];

        for ($i = 0; $i < self::CUSTOMER_COUNT; $i++) {
            $userId = Str::uuid7()->toString();
            $addressId = Str::uuid7()->toString();
            $ids[] = $userId;
            $addressByCustomer[$userId] = $addressId;

            $userRows[] = [
                'id' => $userId,
                'name' => "Perf Customer {$i}",
                'email' => "perf_customer_{$i}@example.test",
                'phone' => '+141655522'.str_pad((string) ($i % 10000), 4, '0', STR_PAD_LEFT),
                'password' => $this->sharedPasswordHash,
                'status' => 'active',
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $addressRows[] = [
                'id' => $addressId,
                'user_id' => $userId,
                'label' => 'Home',
                'street' => "{$i} Perf Street",
                'unit' => null,
                'city' => 'Toronto',
                'state_province' => 'ON',
                'postal_code' => 'M4B1B3',
                'country' => 'CA',
                'lat' => $this->randomLat(),
                'lng' => $this->randomLng(),
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($userRows, 1000) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        foreach (array_chunk($addressRows, 1000) as $chunk) {
            DB::table('addresses')->insert($chunk);
        }

        return [$ids, $addressByCustomer];
    }

    /**
     * @param  list<string>  $customerIds
     * @param  array<string, string>  $addressByCustomer
     * @param  list<string>  $businessIds
     * @param  list<string>  $serviceIds
     * @return list<string>
     */
    private function seedBookings(int $count, array $customerIds, array $addressByCustomer, array $businessIds, array $serviceIds): array
    {
        $ids = [];
        $statuses = ['pending', 'scheduled', 'in_progress', 'completed', 'cancelled', 'declined'];
        $now = now();
        $bar = $this->output->createProgressBar($count);
        $chunk = [];

        for ($i = 0; $i < $count; $i++) {
            $id = Str::uuid7()->toString();
            $ids[] = $id;
            $customerId = $customerIds[array_rand($customerIds)];

            // created_at spread across the last 60 days, weighted so a
            // realistic minority land in the last 24h — that's what makes
            // the "_24h" admin metrics filters actually selective instead
            // of matching everything or nothing.
            $createdAt = (clone $now)->subMinutes(mt_rand(0, 60 * 24 * 60));

            $chunk[] = [
                'id' => $id,
                'booking_number' => 'BK-PERF-'.$i,
                'customer_id' => $customerId,
                'provider_id' => $businessIds[array_rand($businessIds)],
                'service_id' => $serviceIds[array_rand($serviceIds)],
                'scheduled_date' => (clone $now)->addDays(mt_rand(-30, 30))->toDateString(),
                'time_slot_start' => sprintf('%02d:00:00', mt_rand(8, 16)),
                'time_slot_end' => sprintf('%02d:00:00', mt_rand(17, 19)),
                'address_id' => $addressByCustomer[$customerId],
                'lat' => 43.7,
                'lng' => -79.4,
                'notes' => null,
                'status' => $statuses[array_rand($statuses)],
                'payment_status' => 'unpaid',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($chunk) >= 1000) {
                DB::table('bookings')->insert($chunk);
                $chunk = [];
                $bar->advance(1000);
            }
        }

        if ($chunk !== []) {
            DB::table('bookings')->insert($chunk);
        }

        $bar->finish();
        $this->newLine();

        return $ids;
    }

    /**
     * @param  list<string>  $bookingIds
     */
    private function seedPayments(array $bookingIds): void
    {
        $now = now();
        $chunk = [];
        $bar = $this->output->createProgressBar((int) (count($bookingIds) * 0.6));

        foreach ($bookingIds as $i => $bookingId) {
            // ~60% of bookings have a payment, matching "most bookings that
            // reach Accepted get paid" — enough volume to make gmv_24h /
            // payout_volume_24h's filtered scan meaningfully large.
            if ($i % 5 < 3) {
                $createdAt = (clone $now)->subMinutes(mt_rand(0, 60 * 24 * 60));
                $amount = Money::fromMinorUnits(mt_rand(4_000, 40_000), 'CAD');
                $fee = Money::fromMinorUnits((int) round($amount->minorUnits * 0.1), 'CAD');
                $net = Money::fromMinorUnits($amount->minorUnits - $fee->minorUnits, 'CAD');

                $chunk[] = [
                    'id' => Str::uuid7()->toString(),
                    'payable_type' => 'booking',
                    'payable_id' => $bookingId,
                    'stripe_payment_intent_id' => 'pi_perf_'.Str::random(20),
                    'amount' => $amount->toDecimal(),
                    'platform_fee_amount' => $fee->toDecimal(),
                    'provider_net_amount' => $net->toDecimal(),
                    'currency' => 'CAD',
                    'type' => PaymentType::Full->value,
                    'status' => PaymentStatus::Succeeded->value,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                if (count($chunk) >= 1000) {
                    DB::table('payments')->insert($chunk);
                    $chunk = [];
                    $bar->advance(1000);
                }
            }
        }

        if ($chunk !== []) {
            DB::table('payments')->insert($chunk);
        }

        $bar->finish();
        $this->newLine();
    }
}
