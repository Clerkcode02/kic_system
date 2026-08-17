<?php

declare(strict_types=1);

namespace App\Domain\Booking\Events;

use App\Domain\Booking\Models\Booking;
use App\Support\Auditable;
use App\Support\LabelsAuditActor;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated implements Auditable, LabelsAuditActor
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Booking $booking)
    {
    }

    public function auditActorId(): ?string
    {
        return $this->booking->customer_id;
    }

    /**
     * Guest bookings (SRS §6.1) have no customer_id to attribute to — the
     * hashed-email label identifies the actor instead.
     */
    public function auditActorLabel(): ?string
    {
        return $this->booking->actor()->auditActorLabel();
    }

    public function auditAction(): string
    {
        return 'booking.created';
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
        return null;
    }

    public function auditAfterState(): ?array
    {
        return ['status' => $this->booking->status->value];
    }
}
