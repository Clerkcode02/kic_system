<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Guest;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Events\GuestBookingTokenIssued;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Services\BookingAccessTokenService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\LookupGuestBookingRequest;
use App\Support\ValueObjects\BookingActor;
use Illuminate\Http\JsonResponse;

/**
 * SRS §6.1: "always 202 with an identical body whether or not it matched;
 * emails a fresh link on a match. Useless for enumeration."
 *
 * Everything about the response is fixed before the lookup runs — the same
 * status, the same body, and no branch that could change either. A caller
 * who guesses a real booking number learns nothing they didn't already
 * bring; only the mailbox owner receives anything.
 *
 * Note there is deliberately no "not found" path, no validation error that
 * distinguishes a real booking number from a fake one, and no timing-shaped
 * early return before the query.
 */
class GuestBookingLookupController extends Controller
{
    /**
     * @var list<BookingStatus>
     */
    private const CLOSED_STATUSES = [
        BookingStatus::Cancelled,
        BookingStatus::Refunded,
        BookingStatus::Declined,
        BookingStatus::QuotationExpired,
    ];

    public function __invoke(LookupGuestBookingRequest $request, BookingAccessTokenService $tokens): JsonResponse
    {
        $booking = Booking::query()
            ->whereNotNull('guest_email_normalized')
            ->where('booking_number', $request->validated('booking_number'))
            ->where('guest_email_normalized', BookingActor::normalizeEmail((string) $request->validated('email')))
            ->whereNotIn('status', self::CLOSED_STATUSES)
            ->first();

        if ($booking !== null) {
            ['plaintext' => $plaintext] = $tokens->issue($booking, $request->ip());

            GuestBookingTokenIssued::dispatch($booking, $plaintext);
        }

        return response()->json([
            'message' => 'If a booking matches those details, a tracking link has been emailed to it.',
        ], 202);
    }
}
