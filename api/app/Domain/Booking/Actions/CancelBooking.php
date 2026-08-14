<?php

declare(strict_types=1);

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use Illuminate\Validation\ValidationException;

/**
 * SRS §19 cancellation policy: free pre-acceptance, a fee applies
 * post-acceptance, and a provider-initiated cancellation must always carry
 * a reason. `InProgress --> Cancelled` is drawn in §8 as "exceptional
 * cancellation (admin-mediated)" — the state machine allows the edge, this
 * Action is what restricts who may use it.
 *
 * Actually charging the cancellation fee is Payment-module scope
 * (app/Domain/Payment/Actions is still unbuilt) — this Action determines
 * and reports whether a fee applies so the caller/eventual Payment action
 * can act on it, without itself mutating payment state it doesn't own.
 */
final class CancelBooking implements Action
{
    /**
     * @var list<BookingStatus>
     */
    private const POST_ACCEPTANCE_STATUSES = [
        BookingStatus::Accepted,
        BookingStatus::Scheduled,
        BookingStatus::InProgress,
    ];

    public function __construct(private readonly TransitionBookingStatus $transition)
    {
    }

    /**
     * @return array{booking: Booking, cancellation_fee_applied: bool}
     */
    public function handle(Booking $booking, User $actor, ?string $reason): array
    {
        $isAdmin = $actor->hasAnyRole([RoleName::Admin->value, RoleName::SuperAdmin->value]);
        $isProvider = $booking->provider->user_id === $actor->id;

        if ($booking->status === BookingStatus::InProgress && ! $isAdmin) {
            throw new ConflictException(
                'A booking in progress can only be cancelled by an administrator.',
                'exceptional_cancellation_requires_admin',
            );
        }

        if ($isProvider && trim((string) $reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A cancellation reason is required.',
            ]);
        }

        $feeApplies = in_array($booking->status, self::POST_ACCEPTANCE_STATUSES, true);

        $booking = $this->transition->handle($booking, BookingStatus::Cancelled, $actor, $reason);

        return ['booking' => $booking, 'cancellation_fee_applied' => $feeApplies];
    }
}
