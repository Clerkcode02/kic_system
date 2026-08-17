<?php

declare(strict_types=1);

namespace App\Domain\Payment\Notifications;

use App\Domain\Payment\Events\RefundRateSpikeDetected;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class RefundRateSpikeDetectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly RefundRateSpikeDetected $event,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $ratePercent = round($this->event->refundRate * 100, 1);

        return [
            'refund_id' => $this->event->triggeringRefund->id,
            'refund_rate' => $this->event->refundRate,
            'refund_count' => $this->event->refundCount,
            'payment_count' => $this->event->paymentCount,
            'window_hours' => $this->event->windowHours,
            'message' => "Refund rate spiked to {$ratePercent}% ({$this->event->refundCount}/{$this->event->paymentCount}) over the last {$this->event->windowHours}h.",
        ];
    }
}
