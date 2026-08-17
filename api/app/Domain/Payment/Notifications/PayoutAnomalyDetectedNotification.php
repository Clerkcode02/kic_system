<?php

declare(strict_types=1);

namespace App\Domain\Payment\Notifications;

use App\Domain\Payment\Events\PayoutAnomalyDetected;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PayoutAnomalyDetectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PayoutAnomalyDetected $event,
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
        $payout = $this->event->payout;

        return [
            'payout_id' => $payout->id,
            'provider_id' => $payout->provider_id,
            'reason' => $this->event->reason,
            'amount' => $payout->amount->toDecimal(),
            'context' => $this->event->context,
            'message' => "Payout {$payout->id} for provider {$payout->provider_id} flagged as anomalous ({$this->event->reason}).",
        ];
    }
}
