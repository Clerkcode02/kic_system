<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Quotation\Events\QuotationExpiryReminderDue;
use App\Domain\Quotation\Jobs\SendQuotationExpiryReminderJob;
use App\Domain\Quotation\Models\Quotation;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

/**
 * SRS §9: "T-24h and T-2h before valid_until -> customer reminder
 * notification", each sent at most once per quotation (guarded by the
 * reminder_{24h,2h}_sent_at columns, not a fixed time window).
 */
function sentQuotationExpiringAt(\Carbon\Carbon $validUntil, array $extra = []): Quotation
{
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Hourly);

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => BookingStatus::WaitingForCustomer,
    ]);

    return Quotation::factory()->sent()->create(array_merge([
        'booking_id' => $booking->id,
        'valid_until' => $validUntil,
    ], $extra));
}

it('sends the T-24h reminder for a quotation expiring within the window and marks it sent', function () {
    Event::fake([QuotationExpiryReminderDue::class]);

    $quotation = sentQuotationExpiringAt(now()->addHours(10));

    (new SendQuotationExpiryReminderJob())->handle();

    $quotation->refresh();
    expect($quotation->reminder_24h_sent_at)->not->toBeNull();

    Event::assertDispatched(
        QuotationExpiryReminderDue::class,
        fn (QuotationExpiryReminderDue $event) => $event->quotation->id === $quotation->id && $event->hoursBeforeExpiry === 24,
    );
});

it('sends the T-2h reminder once the quotation is within that tighter window', function () {
    Event::fake([QuotationExpiryReminderDue::class]);

    $quotation = sentQuotationExpiringAt(now()->addHours(1), [
        'reminder_24h_sent_at' => now()->subHours(23),
    ]);

    (new SendQuotationExpiryReminderJob())->handle();

    $quotation->refresh();
    expect($quotation->reminder_2h_sent_at)->not->toBeNull();

    Event::assertDispatched(
        QuotationExpiryReminderDue::class,
        fn (QuotationExpiryReminderDue $event) => $event->quotation->id === $quotation->id && $event->hoursBeforeExpiry === 2,
    );
});

it('does not re-send a reminder that was already sent', function () {
    Event::fake([QuotationExpiryReminderDue::class]);

    $quotation = sentQuotationExpiringAt(now()->addHours(10), [
        'reminder_24h_sent_at' => now()->subHour(),
    ]);

    (new SendQuotationExpiryReminderJob())->handle();

    Event::assertNotDispatched(QuotationExpiryReminderDue::class);
});

it('does not send a reminder for a quotation that has already expired or is far from expiring', function () {
    Event::fake([QuotationExpiryReminderDue::class]);

    sentQuotationExpiringAt(now()->subHour());
    sentQuotationExpiringAt(now()->addDays(3));

    (new SendQuotationExpiryReminderJob())->handle();

    Event::assertNotDispatched(QuotationExpiryReminderDue::class);
});
