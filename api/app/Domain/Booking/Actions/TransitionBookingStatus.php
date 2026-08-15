<?php

declare(strict_types=1);

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Events\BookingStatusChanged;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingStatusHistory;
use App\Domain\Booking\StateMachines\BookingStateMachine;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use Illuminate\Support\Facades\DB;

/**
 * The single write path for every booking status change (CLAUDE.md
 * Booking §5 — "Every status change goes through the module's
 * StateMachine. Never `$model->status = x`."). Illegal transitions bubble
 * IllegalStateTransitionException from BookingStateMachine and render as a
 * 409 (see bootstrap/app.php).
 */
final class TransitionBookingStatus implements Action
{
    /**
     * $actor is nullable for system-triggered transitions (scheduled sweeps
     * like ExpireStaleQuotationsJob/ExpireUnquotedBookingsJob) where there
     * is no authenticated user to attribute the change to.
     */
    public function handle(Booking $booking, BookingStatus $to, ?User $actor, ?string $note = null): Booking
    {
        return DB::transaction(function () use ($booking, $to, $actor, $note) {
            $machine = new BookingStateMachine($booking->status);
            $from = $machine->state();
            $machine->transition($to->value);

            // Defense-in-depth for the quote-and-pay path specifically: a
            // fixed-price booking legitimately reaches Scheduled straight
            // from Pending with no payment involved (CLAUDE.md §5 —
            // "fixed-price services skip quoting"), but the Accepted ->
            // Scheduled edge only exists after AcceptQuotation, and must
            // never fire until a payment has actually cleared (CapturePayment,
            // on the payment_intent.succeeded webhook).
            if ($to === BookingStatus::Scheduled && $from === BookingStatus::Accepted->value) {
                $hasSucceededPayment = Payment::query()
                    ->where('payable_type', 'booking')
                    ->where('payable_id', $booking->id)
                    ->where('status', PaymentStatus::Succeeded)
                    ->exists();

                if (! $hasSucceededPayment) {
                    throw new ConflictException(
                        'This booking cannot be scheduled without a succeeded payment.',
                        'payment_not_succeeded',
                    );
                }
            }

            $booking->update(['status' => $to]);

            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'from_status' => $from,
                'to_status' => $to->value,
                'changed_by' => $actor?->id,
                'note' => $note,
            ]);

            // RecordAuditEntry (the global Auditable listener) records this
            // status change from the dispatched event below — see
            // BookingStatusChanged::auditAction()/auditBeforeState() etc.
            BookingStatusChanged::dispatch($booking, $from, $to->value, $actor);

            return $booking->refresh();
        });
    }
}
