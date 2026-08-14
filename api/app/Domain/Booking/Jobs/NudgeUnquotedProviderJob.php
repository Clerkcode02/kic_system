<?php

declare(strict_types=1);

namespace App\Domain\Booking\Jobs;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Events\UnquotedBookingNudgeDue;
use App\Domain\Booking\Models\Booking;
use App\Domain\Platform\Models\PlatformSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SRS §9: "provider reminded daily if no quotation sent within 48h of
 * request". Runs daily; nudges any WaitingForQuotation booking that's past
 * the window and hasn't been nudged in the last 24h (so a daily schedule
 * cadence is enforced even if this job is ever run more than once a day).
 */
class NudgeUnquotedProviderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const DEFAULT_NUDGE_AFTER_HOURS = 48;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $threshold = now()->subHours($this->nudgeAfterHours());

        Booking::query()
            ->where('status', BookingStatus::WaitingForQuotation)
            ->where('created_at', '<=', $threshold)
            ->where(function ($query) {
                $query->whereNull('quotation_nudge_sent_at')
                    ->orWhere('quotation_nudge_sent_at', '<=', now()->subDay());
            })
            ->chunkById(100, function ($bookings) {
                foreach ($bookings as $booking) {
                    $booking->update(['quotation_nudge_sent_at' => now()]);

                    UnquotedBookingNudgeDue::dispatch($booking);
                }
            });
    }

    private function nudgeAfterHours(): int
    {
        $setting = PlatformSetting::query()->where('key', 'booking.quotation_nudge_after_hours')->first();

        return $setting !== null ? (int) $setting->typedValue : self::DEFAULT_NUDGE_AFTER_HOURS;
    }
}
