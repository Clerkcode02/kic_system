<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Events;

use App\Domain\Quotation\Models\Quotation;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotationRevised implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Quotation $quotation,
        public readonly Quotation $previousQuotation,
        public readonly ?User $actor = null,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->actor?->id;
    }

    public function auditAction(): string
    {
        return 'quotation.revised';
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
        return ['previous_quotation_id' => $this->previousQuotation->id, 'revision_number' => $this->previousQuotation->revision_number];
    }

    public function auditAfterState(): ?array
    {
        return ['revision_number' => $this->quotation->revision_number, 'total_amount' => $this->quotation->total_amount->toDecimal()];
    }
}
