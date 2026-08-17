<?php

declare(strict_types=1);

namespace App\Domain\Payment\Events;

use App\Domain\Payment\Models\Payout;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SRS §17 anomaly signal 2: unusual payout patterns. Dispatched by
 * App\Domain\Payment\Services\PayoutAnomalyDetector, run against every
 * payout RunProviderPayoutJob creates. `$reason` is one of
 * PayoutAnomalyDetector::REASON_* and `$context` carries the numbers that
 * justified the flag (trailing average, multiplier, threshold) for the
 * audit trail and the admin notification.
 */
class PayoutAnomalyDetected implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly Payout $payout,
        public readonly string $reason,
        public readonly array $context,
    ) {
    }

    public function auditActorId(): ?string
    {
        return null;
    }

    public function auditAction(): string
    {
        return 'payout.anomaly_detected';
    }

    public function auditableType(): string
    {
        return 'payout';
    }

    public function auditableId(): string
    {
        return $this->payout->id;
    }

    public function auditBeforeState(): ?array
    {
        return null;
    }

    public function auditAfterState(): ?array
    {
        return [
            'reason' => $this->reason,
            'amount' => $this->payout->amount->toDecimal(),
            ...$this->context,
        ];
    }
}
