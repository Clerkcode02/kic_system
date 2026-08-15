<?php

declare(strict_types=1);

namespace App\Domain\Payment\Events;

use App\Domain\Payment\Models\Payout;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayoutCompleted implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Payout $payout)
    {
    }

    public function auditActorId(): ?string
    {
        return null;
    }

    public function auditAction(): string
    {
        return 'payout.completed';
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
        return ['status' => $this->payout->status->value, 'amount' => $this->payout->amount->toDecimal()];
    }
}
