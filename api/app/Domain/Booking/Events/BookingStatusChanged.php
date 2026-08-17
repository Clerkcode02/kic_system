<?php

declare(strict_types=1);

namespace App\Domain\Booking\Events;

use App\Domain\Booking\Models\Booking;
use App\Support\Auditable;
use App\Support\LabelsAuditActor;
use App\Support\ValueObjects\BookingActor;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingStatusChanged implements Auditable, LabelsAuditActor
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $fromStatus,
        public readonly string $toStatus,
        public readonly ?BookingActor $actor,
    ) {
    }

    public function auditActorId(): ?string
    {
        return $this->actor?->auditActorId();
    }

    public function auditActorLabel(): ?string
    {
        return $this->actor?->auditActorLabel();
    }

    public function auditAction(): string
    {
        return 'booking.status_changed';
    }

    public function auditableType(): string
    {
        return 'booking';
    }

    public function auditableId(): string
    {
        return $this->booking->id;
    }

    public function auditBeforeState(): ?array
    {
        return ['status' => $this->fromStatus];
    }

    public function auditAfterState(): ?array
    {
        return ['status' => $this->toStatus];
    }
}
