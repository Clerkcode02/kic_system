<?php

declare(strict_types=1);

namespace App\Domain\Booking\Events;

use App\Domain\Booking\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Not {@see \App\Support\Auditable} — see QuotationExpiryReminderDue for
 * why reminder/nudge events skip the audit trail.
 */
class UnquotedBookingNudgeDue
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Booking $booking)
    {
    }
}
