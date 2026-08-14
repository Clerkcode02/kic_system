<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Actions;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Booking\Actions\TransitionBookingStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Quotation\Events\QuotationRejected;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\Quotation\StateMachines\QuotationStateMachine;
use App\Domain\User\Models\User;
use App\Support\Action;
use Illuminate\Support\Facades\DB;

/**
 * SRS §19: "rejection allows revise-or-cancel" — the booking is routed
 * back to WaitingForQuotation (not declined outright) so the provider can
 * send a revised quote via ReviseQuotation; the customer can still cancel
 * the booking entirely through the separate CancelBooking action.
 */
final class RejectQuotation implements Action
{
    public function __construct(private readonly TransitionBookingStatus $transition)
    {
    }

    public function handle(Quotation $quotation, User $actor, ?string $reason = null): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor, $reason) {
            $machine = new QuotationStateMachine($quotation->status);
            $machine->transition('rejected');
            $quotation->update(['status' => 'rejected']);

            $this->transition->handle(
                $quotation->booking,
                BookingStatus::WaitingForQuotation,
                $actor,
                $reason ?? 'Customer rejected the quotation.',
            );

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'quotation.rejected',
                'auditable_type' => 'quotation',
                'auditable_id' => $quotation->id,
                'before_state' => ['status' => 'sent'],
                'after_state' => ['status' => 'rejected', 'reason' => $reason],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            QuotationRejected::dispatch($quotation->fresh());

            return $quotation->fresh(['lineItems', 'booking']);
        });
    }
}
