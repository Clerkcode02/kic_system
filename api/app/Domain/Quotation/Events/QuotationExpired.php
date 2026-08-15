<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Events;

use App\Domain\Quotation\Models\Quotation;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotationExpired implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Quotation $quotation)
    {
    }

    public function auditActorId(): ?string
    {
        return null;
    }

    public function auditAction(): string
    {
        return 'quotation.expired';
    }

    public function auditableType(): string
    {
        return 'quotation';
    }

    public function auditableId(): string
    {
        return $this->quotation->id;
    }

    public function auditBeforeState(): ?array
    {
        return ['status' => 'sent'];
    }

    public function auditAfterState(): ?array
    {
        return ['status' => 'expired'];
    }
}
