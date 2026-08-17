<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Events\UnquotedBookingNudgeDue;
use App\Domain\Booking\Jobs\NudgeUnquotedProviderJob;
use App\Domain\Booking\Models\Booking;
use App\Domain\Catalog\Enums\ServicePricingType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

/**
 * SRS §9 / CLAUDE.md §5: "If a provider sends no quote within 48h they're
 * reminded daily." The 48h window and the "don't re-nudge within 24h"
 * cadence guard are both exercised here.
 */
function unquotedBookingCreatedAt(\Carbon\Carbon $createdAt, ?\Carbon\Carbon $lastNudgedAt = null): Booking
{
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Hourly);

    return Booking::factory()->waitingForQuotation()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'created_at' => $createdAt,
        'quotation_nudge_sent_at' => $lastNudgedAt,
    ]);
}

it('nudges a provider whose booking has waited past the 48h window and never been nudged', function () {
    Event::fake([UnquotedBookingNudgeDue::class]);

    $overdue = unquotedBookingCreatedAt(now()->subHours(50));

    (new NudgeUnquotedProviderJob())->handle();

    expect($overdue->fresh()->quotation_nudge_sent_at)->not->toBeNull();
    Event::assertDispatched(UnquotedBookingNudgeDue::class, fn (UnquotedBookingNudgeDue $event) => $event->booking->id === $overdue->id);
});

it('does not nudge a booking still within the 48h grace window', function () {
    Event::fake([UnquotedBookingNudgeDue::class]);

    $recent = unquotedBookingCreatedAt(now()->subHours(10));

    (new NudgeUnquotedProviderJob())->handle();

    expect($recent->fresh()->quotation_nudge_sent_at)->toBeNull();
    Event::assertNotDispatched(UnquotedBookingNudgeDue::class);
});

it('does not re-nudge within 24h of the last nudge, but does once that cools down', function () {
    Event::fake([UnquotedBookingNudgeDue::class]);

    $recentlyNudged = unquotedBookingCreatedAt(now()->subDays(3), now()->subHours(5));
    $dueAgain = unquotedBookingCreatedAt(now()->subDays(3), now()->subDays(2));

    (new NudgeUnquotedProviderJob())->handle();

    expect($recentlyNudged->fresh()->quotation_nudge_sent_at->diffInHours(now()))->toBeGreaterThan(4.0);
    Event::assertDispatched(UnquotedBookingNudgeDue::class, fn (UnquotedBookingNudgeDue $event) => $event->booking->id === $dueAgain->id);
    Event::assertNotDispatched(UnquotedBookingNudgeDue::class, fn (UnquotedBookingNudgeDue $event) => $event->booking->id === $recentlyNudged->id);
});

it('ignores bookings that are not waiting for a quotation', function () {
    Event::fake([UnquotedBookingNudgeDue::class]);

    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Fixed);

    Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => BookingStatus::Scheduled,
        'created_at' => now()->subDays(5),
    ]);

    (new NudgeUnquotedProviderJob())->handle();

    Event::assertNotDispatched(UnquotedBookingNudgeDue::class);
});
