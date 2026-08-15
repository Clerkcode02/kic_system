<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Jobs;

use App\Domain\Booking\Actions\TransitionBookingStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Quotation\Events\QuotationExpired;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\Quotation\StateMachines\QuotationStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * SRS §9/§14/§15: scheduled sweep of `sent` quotations whose `valid_until`
 * has passed. Marks the quotation Expired and routes the booking
 * WaitingForCustomer --> QuotationExpired (§8 diagram) — a booking only
 * reaches WaitingForCustomer via a sent quotation, so that transition is
 * always legal here.
 */
class ExpireStaleQuotationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(TransitionBookingStatus $transition): void
    {
        Quotation::query()
            ->where('status', 'sent')
            ->where('valid_until', '<', now())
            ->with('booking')
            ->chunkById(100, function ($quotations) use ($transition) {
                foreach ($quotations as $quotation) {
                    DB::transaction(function () use ($quotation, $transition) {
                        $machine = new QuotationStateMachine($quotation->status);

                        if (! $machine->canTransition('expired')) {
                            return;
                        }

                        $machine->transition('expired');
                        $quotation->update(['status' => 'expired']);

                        $booking = $quotation->booking;

                        if ($booking->status === BookingStatus::WaitingForCustomer) {
                            $transition->handle($booking, BookingStatus::QuotationExpired, null, 'Quotation validity window elapsed.');
                        }

                        QuotationExpired::dispatch($quotation->fresh());
                    });
                }
            });
    }
}
