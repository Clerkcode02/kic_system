<?php

declare(strict_types=1);

namespace App\Domain\Booking\Events;

use App\Domain\Booking\Models\Booking;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Booking $booking)
    {
    }
}
