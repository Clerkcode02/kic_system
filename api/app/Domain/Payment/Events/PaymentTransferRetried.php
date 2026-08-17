<?php

declare(strict_types=1);

namespace App\Domain\Payment\Events;

use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentTransferRetried implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly User $actor,
        public readonly string $failedTransferId,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->actor->id;
    }

    public function auditAction(): string
    {
        return 'payment.transfer_retry';
    }

    public function auditableType(): string
    {
        return 'payment';
    }

    public function auditableId(): string
    {
        return $this->payment->id;
    }

    public function auditBeforeState(): ?array
    {
        return ['status' => 'failed', 'stripe_transfer_id' => $this->failedTransferId];
    }

    public function auditAfterState(): ?array
    {
        return ['status' => $this->payment->status->value, 'stripe_transfer_id' => $this->payment->stripe_transfer_id];
    }
}
