<?php

declare(strict_types=1);

namespace App\Domain\Booking\StateMachines;

use App\Domain\Booking\Enums\BookingStatus;
use App\Support\AbstractStateMachine;

/**
 * SRS §8 transition graph, keyed by BookingStatus::value.
 *
 * Two notes on edges that aren't literally drawn in the §8 diagram but are
 * required by the §19 validation rules it sits alongside:
 *
 * - "Free cancel pre-acceptance" (§19) means every pre-acceptance state
 *   (Pending, WaitingForQuotation, QuotationSent, WaitingForCustomer) must
 *   also reach Cancelled, not just Scheduled as the diagram's single
 *   `Scheduled --> Cancelled` edge shows.
 * - `InProgress --> Cancelled` is drawn as "exceptional cancellation
 *   (admin-mediated)" — the graph allows the transition; restricting it to
 *   admin actors is an authorization concern handled by CancelBooking, not
 *   the state machine.
 * - `WaitingForQuotation --> QuotationExpired` isn't in the §8 diagram
 *   (which only draws the expiry edge out of WaitingForCustomer, i.e. after
 *   a quote was sent), but §9's "booking auto-expires after 5 days" rule
 *   covers a provider who never sends a quote at all — ExpireUnquotedBookingsJob
 *   needs this edge to sweep those.
 */
final class BookingStateMachine extends AbstractStateMachine
{
    /**
     * @var array<string, array<int, string>>
     */
    protected array $transitions = [
        'pending' => ['waiting_for_quotation', 'scheduled', 'cancelled'],
        'waiting_for_quotation' => ['quotation_sent', 'quotation_expired', 'cancelled'],
        'quotation_sent' => ['waiting_for_customer', 'cancelled'],
        'waiting_for_customer' => ['accepted', 'declined', 'waiting_for_quotation', 'quotation_expired', 'cancelled'],
        'accepted' => ['scheduled', 'cancelled'],
        'scheduled' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => ['refunded'],
        'declined' => [],
        'quotation_expired' => [],
        'cancelled' => [],
        'refunded' => [],
    ];

    public function __construct(BookingStatus|string $initialState)
    {
        parent::__construct($initialState instanceof BookingStatus ? $initialState->value : $initialState);
    }
}
