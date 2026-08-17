<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Booking;

use App\Domain\Booking\Actions\CreateBookingRequest as CreateBookingRequestAction;
use App\Domain\Booking\Events\GuestBookingTokenIssued;
use App\Domain\Booking\Services\BookingAccessTokenService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\GuestBookingResource;
use Illuminate\Http\JsonResponse;

/**
 * SRS §6.1: one creation endpoint, two actor kinds. The request decides
 * which actor it carries; the Action is identical for both. The only
 * branch here is at the *edge* — a guest additionally gets a booking access
 * token, returned in plaintext exactly once and never again.
 */
class StoreBookingController extends Controller
{
    public function __invoke(
        StoreBookingRequest $request,
        CreateBookingRequestAction $action,
        BookingAccessTokenService $tokens,
    ): JsonResponse {
        $actor = $request->actor();

        $booking = $action->handle($actor, $request->validated());

        if (! $actor->isGuest()) {
            return (new BookingResource($booking->load(['service', 'provider', 'customer', 'statusHistory'])))
                ->response()
                ->setStatusCode(201);
        }

        ['plaintext' => $plaintext] = $tokens->issue($booking, $request->ip());

        // Emails the tracking link. The plaintext is passed on the event
        // rather than looked up, because it is not recoverable from storage.
        GuestBookingTokenIssued::dispatch($booking, $plaintext);

        $booking->load(['service', 'provider', 'statusHistory', 'quotations.lineItems']);

        // The plaintext lives in this response body and in the tracking
        // email, and nowhere else — it is never stored, re-derivable, or
        // logged (CLAUDE.md §2).
        return (new GuestBookingResource($booking))
            ->additional(['meta' => ['access_token' => $plaintext]])
            ->response()
            ->setStatusCode(201);
    }
}
