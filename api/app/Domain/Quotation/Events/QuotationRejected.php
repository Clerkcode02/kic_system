<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Events;

use App\Domain\Quotation\Models\Quotation;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotationRejected implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Quotation $quotation,
        public readonly ?User $actor = null,
        public readonly ?string $reason = null,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->actor?->id;
    }

    public function auditAction(): string
    {
        return 'quotation.rejected';
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
        return ['status' => 'rejected', 'reason' => $this->reason];
    }
}
