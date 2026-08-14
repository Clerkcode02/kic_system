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
use App\Domain\User\Models\Address;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;

/**
 * DoubleBookingValidator's guard only proves itself under a genuine race
 * between two separate DB connections. RefreshDatabase wraps an entire
 * test in one connection's still-open transaction, which a second,
 * independently-connected process could never see the setup rows from —
 * so, uniquely among this suite's Feature tests, this file manages its own
 * committed setup and teardown instead of using that trait, and forks two
 * real child processes (each with its own DB connection) to attempt the
 * same slot at the same time.
 */
it('lets exactly one of two truly concurrent requests for the same slot succeed', function () {
    if (! function_exists('pcntl_fork')) {
        test()->markTestSkipped('pcntl extension is not available in this environment.');
    }

    app(RoleAndPermissionSeeder::class)->run();

    $customerA = User::factory()->customer()->create();
    $customerA->assignRole(RoleName::Customer->value);
    $customerB = User::factory()->customer()->create();
    $customerB->assignRole(RoleName::Customer->value);

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

    // Without RefreshDatabase this row is never rolled back, so it's
    // pinned inactive — ServiceFactory would otherwise spin up its own
    // *active* top-level Category by default, permanently leaking into
    // any other test's unscoped "all active categories" query.
    $category = Category::factory()->create(['is_active' => false]);
    $service = Service::factory()->create([
        'business_id' => $business->id,
        'category_id' => $category->id,
        'pricing_type' => ServicePricingType::Fixed,
        'is_active' => true,
    ]);

    $addressA = Address::factory()->for($customerA, 'user')->create();
    $addressB = Address::factory()->for($customerB, 'user')->create();

    $date = now()->addDays(5)->toDateString();

    $data = [
        'service_id' => $service->id,
        'scheduled_date' => $date,
        'time_slot_start' => '10:00:00',
        'time_slot_end' => '11:00:00',
    ];

    $resultFileA = tempnam(sys_get_temp_dir(), 'booking_a_');
    $resultFileB = tempnam(sys_get_temp_dir(), 'booking_b_');

    // Neither forked child may inherit the parent's live DB socket — each
    // reconnects independently right after the fork.
    DB::disconnect();

    $pidA = pcntl_fork();

    if ($pidA === -1) {
        test()->fail('Failed to fork process A.');
    }

    if ($pidA === 0) {
        attemptConcurrentBooking($customerA->id, [...$data, 'address_id' => $addressA->id], $resultFileA);
        exit(0);
    }

    $pidB = pcntl_fork();

    if ($pidB === -1) {
        test()->fail('Failed to fork process B.');
    }

    if ($pidB === 0) {
        attemptConcurrentBooking($customerB->id, [...$data, 'address_id' => $addressB->id], $resultFileB);
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

    // No manual cleanup: booking_status_history is append-only (a DB
    // trigger rejects UPDATE/DELETE), so a hard-delete of the winning
    // booking would fail on its own cascade. The rows are harmless,
    // uniquely-generated leftovers — every other test scopes its queries
    // to its own factory-created records rather than asserting global
    // table counts, so they're unaffected.
});

/**
 * @param  array<string, mixed>  $data
 */
function attemptConcurrentBooking(string $customerId, array $data, string $resultFile): void
{
    DB::reconnect();

    try {
        $customer = User::query()->findOrFail($customerId);
        $booking = app(CreateBookingRequest::class)->handle($customer, $data);
        file_put_contents($resultFile, json_encode(['outcome' => 'succeeded', 'booking_id' => $booking->id]));
    } catch (\Illuminate\Validation\ValidationException) {
        file_put_contents($resultFile, json_encode(['outcome' => 'failed']));
    } catch (\Throwable $e) {
        file_put_contents($resultFile, json_encode(['outcome' => 'error', 'message' => $e->getMessage()]));
    }
}
