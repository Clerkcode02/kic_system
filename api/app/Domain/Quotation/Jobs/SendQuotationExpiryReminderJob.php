<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Jobs;

use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\Quotation\Events\QuotationExpiryReminderDue;
use App\Domain\Quotation\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SRS §9: "T-24h and T-2h before `valid_until` -> customer reminder
 * notification", both windows configurable via platform_settings. Each
 * quotation gets each reminder at most once, guarded by the
 * reminder_{24h,2h}_sent_at columns rather than a fixed time window, so a
 * job that runs late (or a missed tick) doesn't skip the reminder — it
 * only skips ever re-sending it.
 */
class SendQuotationExpiryReminderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const DEFAULT_FIRST_REMINDER_HOURS = 24;

    private const DEFAULT_FINAL_REMINDER_HOURS = 2;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $this->sendDue($this->settingHours('quotation.reminder_window_hours', self::DEFAULT_FIRST_REMINDER_HOURS), 'reminder_24h_sent_at');
        $this->sendDue($this->settingHours('quotation.final_reminder_window_hours', self::DEFAULT_FINAL_REMINDER_HOURS), 'reminder_2h_sent_at');
    }

    private function settingHours(string $key, int $default): int
    {
        $setting = PlatformSetting::query()->where('key', $key)->first();

        return $setting !== null ? (int) $setting->typedValue : $default;
    }

    private function sendDue(int $hoursBeforeExpiry, string $column): void
    {
        Quotation::query()
            ->where('status', 'sent')
            ->whereNull($column)
            ->where('valid_until', '<=', now()->addHours($hoursBeforeExpiry))
            ->where('valid_until', '>', now())
            ->chunkById(100, function ($quotations) use ($column, $hoursBeforeExpiry) {
                foreach ($quotations as $quotation) {
                    $quotation->update([$column => now()]);

                    QuotationExpiryReminderDue::dispatch($quotation, $hoursBeforeExpiry);
                }
            });
    }
}
