<?php

declare(strict_types=1);

namespace App\Domain\Payment\Events;

use App\Domain\Payment\Models\Payment;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentSucceeded implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Payment $payment)
    {
    }

    public function auditActorId(): ?string
    {
        return null;
    }

    public function auditAction(): string
    {
        return 'payment.succeeded';
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
        return ['status' => 'pending'];
    }

    public function auditAfterState(): ?array
    {
        return ['status' => 'succeeded', 'amount' => $this->payment->amount->toDecimal()];
    }
}
