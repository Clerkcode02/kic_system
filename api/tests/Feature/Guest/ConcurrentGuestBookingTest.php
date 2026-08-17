<?php

declare(strict_types=1);

use App\Domain\Booking\Actions\CreateBookingRequest;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\ProviderAvailability;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Service;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use App\Support\ValueObjects\BookingActor;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The guest counterpart to ConcurrentBookingTest. Guest booking creation is
 * public and rate-limited rather than authenticated, which makes it by far
 * the easiest endpoint on the platform to hammer — so the double-booking
 * guard has to hold for guests specifically, not merely "for bookings".
 *
 * Same harness rationale as ConcurrentBookingTest: RefreshDatabase would
 * hide the setup rows from a second connection, so this file commits its
 * own setup and forks two real processes.
 */
it('lets exactly one of two truly concurrent guest requests for the same slot succeed', function () {
    if (! function_exists('pcntl_fork')) {
        test()->markTestSkipped('pcntl extension is not available in this environment.');
    }

    app(RoleAndPermissionSeeder::class)->run();

    $providerUser = User::factory()->provider()->create();
    $providerUser->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->verified()->create([
        'user_id' => $providerUser->id,
        'max_bookings_per_day' => 10,
    ]);

    foreach (range(0, 6) as $day) {
        ProviderAvailability::factory()->create([
            'business_id' => $business->id,
            'day_of_week' => $day,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
        ]);
    }

    // Pinned inactive for the same reason as ConcurrentBookingTest: without
    // RefreshDatabase this row never rolls back.
    //
    // The slug is explicitly unique rather than factory-generated.
    // CategoryFactory derives it from `fake()->unique()->words(2)`, whose
    // uniqueness register resets every test — so a permanently-committed
    // row like this one can, and does, collide with a slug a later test
    // generates, failing that unrelated test on `categories.slug`.
    $category = Category::factory()->create([
        'is_active' => false,
        'name' => 'Concurrent Guest Fixture '.Str::uuid7(),
        'slug' => 'concurrent-guest-fixture-'.Str::uuid7(),
    ]);
    $service = Service::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'pricing_type' => ServicePricingType::Fixed,
        'is_active' => true,
    ]);

    $date = now()->addDays(6)->toDateString();

    $data = [
        'service_id' => $service->id,
        'scheduled_date' => $date,
        'time_slot_start' => '14:00:00',
        'time_slot_end' => '15:00:00',
        'service_address' => [
            'line1' => '55 Front St W',
            'city' => 'Toronto',
            'province' => 'ON',
            'postal_code' => 'M5J 1E6',
            'lat' => 43.6426,
            'lng' => -79.3871,
        ],
    ];

    $resultFileA = tempnam(sys_get_temp_dir(), 'guest_booking_a_');
    $resultFileB = tempnam(sys_get_temp_dir(), 'guest_booking_b_');

    DB::disconnect();

    $pidA = pcntl_fork();

    if ($pidA === -1) {
        test()->fail('Failed to fork process A.');
    }

    if ($pidA === 0) {
        attemptConcurrentGuestBooking('guest-a@example.com', $data, $resultFileA);
        exit(0);
    }

    $pidB = pcntl_fork();

    if ($pidB === -1) {
        test()->fail('Failed to fork process B.');
    }

    if ($pidB === 0) {
        attemptConcurrentGuestBooking('guest-b@example.com', $data, $resultFileB);
        exit(0);
    }

    pcntl_waitpid($pidA, $statusA);
    pcntl_waitpid($pidB, $statusB);

    DB::reconnect();

    $outcomeA = json_decode((string) file_get_contents($resultFileA), true)['outcome'] ?? null;
    $outcomeB = json_decode((string) file_get_contents($resultFileB), true)['outcome'] ?? null;

    @unlink($resultFileA);
    @unlink($resultFileB);

    $outcomes = [$outcomeA, $outcomeB];
    sort($outcomes);

    expect($outcomes)->toBe(['failed', 'succeeded']);
    expect(
        Booking::query()->where('provider_id', $business->id)->where('scheduled_date', $date)->count()
    )->toBe(1);
});

/**
 * @param  array<string, mixed>  $data
 */
function attemptConcurrentGuestBooking(string $email, array $data, string $resultFile): void
{
    DB::reconnect();

    try {
        $actor = BookingActor::guest('Concurrent Guest', $email, '+14165550100');
        $booking = app(CreateBookingRequest::class)->handle($actor, $data);
        file_put_contents($resultFile, json_encode(['outcome' => 'succeeded', 'booking_id' => $booking->id]));
    } catch (\Illuminate\Validation\ValidationException) {
        file_put_contents($resultFile, json_encode(['outcome' => 'failed']));
    } catch (\Throwable $e) {
        file_put_contents($resultFile, json_encode(['outcome' => 'error', 'message' => $e->getMessage()]));
    }
}
