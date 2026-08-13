<?php

declare(strict_types=1);

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\ProviderAvailability;
use App\Domain\Business\Models\ProviderAvailabilityOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

/**
 * Next occurrence of the given ISO-8601 day-of-week number (0=Sunday), at
 * least a week out so "after_or_equal:today" validation never flakes near
 * midnight in CI.
 */
function nextDateForDayOfWeek(int $dayOfWeek): \Illuminate\Support\Carbon
{
    $date = now()->addWeek()->startOfDay();

    while ($date->dayOfWeek !== $dayOfWeek) {
        $date = $date->addDay();
    }

    return $date;
}

it('returns bookable slots within the weekly working window', function () {
    $business = Business::factory()->verified()->create(['max_bookings_per_day' => 10]);
    $date = nextDateForDayOfWeek(1);

    ProviderAvailability::factory()->create([
        'business_id' => $business->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'is_active' => true,
    ]);

    $response = $this->getJson("/api/v1/providers/{$business->id}/availability?date={$date->toDateString()}")
        ->assertOk();

    // 09:00-10:00 in 30-minute slots: [09:00-09:30, 09:30-10:00].
    expect($response->json('data.slots'))->toHaveCount(2);
});

it('excludes a slot already occupied by an active booking', function () {
    $business = Business::factory()->verified()->create(['max_bookings_per_day' => 10]);
    $date = nextDateForDayOfWeek(1);

    ProviderAvailability::factory()->create([
        'business_id' => $business->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'is_active' => true,
    ]);

    Booking::factory()->create([
        'provider_id' => $business->id,
        'scheduled_date' => $date->toDateString(),
        'time_slot_start' => '09:00:00',
        'time_slot_end' => '09:30:00',
        'status' => 'pending',
    ]);

    $response = $this->getJson("/api/v1/providers/{$business->id}/availability?date={$date->toDateString()}")
        ->assertOk();

    $starts = collect($response->json('data.slots'))->map(fn ($slot) => Carbon\CarbonImmutable::parse($slot['start'])->format('H:i'));

    expect($starts)->not->toContain('09:00')
        ->and($starts)->toContain('09:30');
});

it('busts the cached availability the moment a new booking is created', function () {
    $business = Business::factory()->verified()->create(['max_bookings_per_day' => 10]);
    $date = nextDateForDayOfWeek(1);

    ProviderAvailability::factory()->create([
        'business_id' => $business->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'is_active' => true,
    ]);

    // Warm the cache before the booking exists — 09:00 is free.
    $before = $this->getJson("/api/v1/providers/{$business->id}/availability?date={$date->toDateString()}")->assertOk();
    $beforeStarts = collect($before->json('data.slots'))->map(fn ($slot) => Carbon\CarbonImmutable::parse($slot['start'])->format('H:i'));
    expect($beforeStarts)->toContain('09:00');

    Booking::factory()->create([
        'provider_id' => $business->id,
        'scheduled_date' => $date->toDateString(),
        'time_slot_start' => '09:00:00',
        'time_slot_end' => '09:30:00',
        'status' => 'pending',
    ]);

    // Still within the 5-minute TTL — if the create hadn't busted the cache,
    // this would still return the stale (now-wrong) result.
    $after = $this->getJson("/api/v1/providers/{$business->id}/availability?date={$date->toDateString()}")->assertOk();
    $afterStarts = collect($after->json('data.slots'))->map(fn ($slot) => Carbon\CarbonImmutable::parse($slot['start'])->format('H:i'));

    expect($afterStarts)->not->toContain('09:00');
});

it('returns no slots on a blackout date, even with weekly hours defined', function () {
    $business = Business::factory()->verified()->create(['max_bookings_per_day' => 10]);
    $date = nextDateForDayOfWeek(1);

    ProviderAvailability::factory()->create([
        'business_id' => $business->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'is_active' => true,
    ]);

    ProviderAvailabilityOverride::factory()->blackout()->create([
        'business_id' => $business->id,
        'date' => $date->toDateString(),
    ]);

    $response = $this->getJson("/api/v1/providers/{$business->id}/availability?date={$date->toDateString()}")
        ->assertOk();

    expect($response->json('data.slots'))->toBeEmpty();
});

it('returns no slots once the daily booking cap is reached', function () {
    $business = Business::factory()->verified()->create(['max_bookings_per_day' => 1]);
    $date = nextDateForDayOfWeek(1);

    ProviderAvailability::factory()->create([
        'business_id' => $business->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'is_active' => true,
    ]);

    Booking::factory()->create([
        'provider_id' => $business->id,
        'scheduled_date' => $date->toDateString(),
        'time_slot_start' => '13:00:00',
        'time_slot_end' => '13:30:00',
        'status' => 'pending',
    ]);

    $response = $this->getJson("/api/v1/providers/{$business->id}/availability?date={$date->toDateString()}")
        ->assertOk();

    expect($response->json('data.slots'))->toBeEmpty();
});

it('rejects a request for a past date', function () {
    $business = Business::factory()->verified()->create();

    $this->getJson("/api/v1/providers/{$business->id}/availability?date=".now()->subDay()->toDateString())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});
