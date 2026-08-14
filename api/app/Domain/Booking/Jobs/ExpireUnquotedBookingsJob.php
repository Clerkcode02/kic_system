<?php

declare(strict_types=1);

namespace App\Domain\Booking\Jobs;

use App\Domain\Booking\Actions\TransitionBookingStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Platform\Models\PlatformSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SRS §9: "booking auto-expires after 5 days" if the provider never sends
 * a quotation at all — distinct from ExpireStaleQuotationsJob, which
 * handles a quotation that WAS sent but timed out unanswered. The 5-day
 * window is anchored to the most recent time the booking entered
 * WaitingForQuotation (a booking can re-enter that state after a rejected
 * quotation), read from BookingStatusHistory rather than `created_at`.
 */
class ExpireUnquotedBookingsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const DEFAULT_AUTO_EXPIRE_DAYS = 5;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(TransitionBookingStatus $transition): void
    {
        $threshold = now()->subDays($this->autoExpireDays());

        Booking::query()
            ->where('status', BookingStatus::WaitingForQuotation)
            ->chunkById(100, function ($bookings) use ($transition, $threshold) {
                foreach ($bookings as $booking) {
                    $enteredAt = $booking->statusHistory()
                        ->where('to_status', BookingStatus::WaitingForQuotation->value)
                        ->latest('created_at')
                        ->value('created_at') ?? $booking->created_at;

                    if ($enteredAt <= $threshold) {
                        $transition->handle($booking, BookingStatus::QuotationExpired, null, 'No quotation sent within the auto-expiry window.');
                    }
                }
            });
    }

    private function autoExpireDays(): int
    {
        $setting = PlatformSetting::query()->where('key', 'booking.auto_expire_days')->first();

        return $setting !== null ? (int) $setting->typedValue : self::DEFAULT_AUTO_EXPIRE_DAYS;
    }
}
