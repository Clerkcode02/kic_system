<?php

declare(strict_types=1);

namespace App\Domain\Payment\Events;

use App\Domain\Payment\Models\Refund;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SRS §17 anomaly signal 3: refund-rate spike. Dispatched by
 * App\Domain\Payment\Services\RefundRateMonitor when the rolling 24h
 * refund rate (refunds / payments) crosses a threshold. `$triggeringRefund`
 * anchors the audit entry to the refund that pushed the rate over —
 * the event describes a platform-wide rate, not one payment, so there is
 * no single "the" auditable payment to point at otherwise.
 */
class RefundRateSpikeDetected implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Refund $triggeringRefund,
        public readonly float $refundRate,
        public readonly int $refundCount,
        public readonly int $paymentCount,
        public readonly int $windowHours,
    ) {
    }

    public function auditActorId(): ?string
    {
        return null;
    }

    public function auditAction(): string
    {
        return 'payment.refund_rate_spike_detected';
    }

    public function auditableType(): string
    {
        return 'refund';
    }

    public function auditableId(): string
    {
        return $this->triggeringRefund->id;
    }

    public function auditBeforeState(): ?array
    {
        return null;
    }

    public function auditAfterState(): ?array
    {
        return [
            'refund_rate' => round($this->refundRate, 4),
            'refund_count' => $this->refundCount,
            'payment_count' => $this->paymentCount,
            'window_hours' => $this->windowHours,
        ];
    }
}
