<?php

declare(strict_types=1);

namespace App\Domain\Payment\Events;

use App\Domain\Payment\Models\Refund;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundProcessed implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Refund $refund,
        public readonly ?User $actor = null,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->actor?->id;
    }

    public function auditAction(): string
    {
        return 'payment.refunded';
    }

    public function auditableType(): string
    {
        return 'payment';
    }

    public function auditableId(): string
    {
        return $this->refund->payment_id;
    }

    public function auditBeforeState(): ?array
    {
        return ['status' => 'succeeded'];
    }

    public function auditAfterState(): ?array
    {
        return ['status' => 'refunded', 'refund_id' => $this->refund->id, 'amount' => $this->refund->amount->toDecimal()];
    }
}
