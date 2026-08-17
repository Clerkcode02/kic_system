<?php

declare(strict_types=1);

use App\Domain\Booking\Actions\TransitionBookingStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Jobs\ExpireUnquotedBookingsJob;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingStatusHistory;
use App\Domain\Catalog\Enums\ServicePricingType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

/**
 * SRS §9 / CLAUDE.md §8: a booking that never received a quotation
 * auto-expires 5 days after it most recently entered WaitingForQuotation,
 * distinct from ExpireStaleQuotationsJob (which handles a quotation that
 * WAS sent but timed out). The window is read from BookingStatusHistory,
 * not `created_at`.
 */
function unquotedBookingWaitingSince(\Carbon\Carbon $enteredAt): Booking
{
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Hourly);

    $booking = Booking::factory()->waitingForQuotation()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'created_at' => $enteredAt,
    ]);

    BookingStatusHistory::factory()->create([
        'booking_id' => $booking->id,
        'from_status' => 'pending',
        'to_status' => BookingStatus::WaitingForQuotation->value,
        'created_at' => $enteredAt,
    ]);

    return $booking;
}

it('auto-expires a booking that has been waiting for a quotation past the 5-day window', function () {
    $stale = unquotedBookingWaitingSince(now()->subDays(6));
    $fresh = unquotedBookingWaitingSince(now()->subDays(2));

    (new ExpireUnquotedBookingsJob())->handle(app(TransitionBookingStatus::class));

    expect($stale->fresh()->status)->toBe(BookingStatus::QuotationExpired);
    expect($fresh->fresh()->status)->toBe(BookingStatus::WaitingForQuotation);
});

it('anchors the auto-expiry window to the latest re-entry into WaitingForQuotation, not created_at', function () {
    // Booking was originally created 10 days ago, but re-entered
    // WaitingForQuotation (e.g. after a rejected quotation) only 1 day ago
    // — it must NOT be expired even though created_at is far in the past.
    $booking = unquotedBookingWaitingSince(now()->subDays(10));
    $booking->statusHistory()->create([
        'from_status' => 'quotation_sent',
        'to_status' => BookingStatus::WaitingForQuotation->value,
        'created_at' => now()->subDay(),
    ]);

    (new ExpireUnquotedBookingsJob())->handle(app(TransitionBookingStatus::class));

    expect($booking->fresh()->status)->toBe(BookingStatus::WaitingForQuotation);
});

it('leaves bookings in other statuses untouched', function () {
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Fixed);

    $scheduled = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => BookingStatus::Scheduled,
        'created_at' => now()->subDays(10),
    ]);

    (new ExpireUnquotedBookingsJob())->handle(app(TransitionBookingStatus::class));

    expect($scheduled->fresh()->status)->toBe(BookingStatus::Scheduled);
});
