<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Guest;

use App\Domain\Quotation\Actions\AcceptQuotation;
use App\Domain\Quotation\Actions\RejectQuotation;
use App\Domain\Quotation\Models\Quotation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\RejectGuestQuotationRequest;
use App\Http\Resources\GuestBookingResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * SRS §6.1: a guest may accept or reject the quotation on the booking their
 * token opens — and only that booking's. The quotation id in the URL is
 * *addressing*, never authorization: it is checked against the
 * token-resolved booking, and a mismatch is a 404 like every other miss.
 *
 * Both endpoints call the same {@see AcceptQuotation}/{@see RejectQuotation}
 * Actions as the registered path, including the identical server-side total
 * recomputation and destination-charge intent creation.
 */
class GuestQuotationController extends Controller
{
    use ResolvesGuestBooking;

    public function accept(Request $request, string $quotation, AcceptQuotation $action): GuestBookingResource
    {
        $booking = $this->guestBooking($request);
        $model = $this->quotationOnBooking($quotation, $request);

        $result = $action->handle($model, $this->guestActor($request));

        return (new GuestBookingResource($booking->fresh([
            'service', 'provider', 'statusHistory', 'quotations.lineItems',
        ])))->additional([
            'meta' => [
                'payment' => [
                    'client_secret' => $result['client_secret'],
                    'amount' => $result['payment']->amount->toDecimal(),
                    'currency' => $result['payment']->currency,
                    'status' => $result['payment']->status,
                ],
            ],
        ]);
    }

    public function reject(RejectGuestQuotationRequest $request, string $quotation, RejectQuotation $action): GuestBookingResource
    {
        $booking = $this->guestBooking($request);
        $model = $this->quotationOnBooking($quotation, $request);

        $action->handle($model, $this->guestActor($request), $request->validated('reason'));

        return new GuestBookingResource($booking->fresh([
            'service', 'provider', 'statusHistory', 'quotations.lineItems',
        ]));
    }

    private function quotationOnBooking(string $quotationId, Request $request): Quotation
    {
        // Guard before querying: `id` is a uuid column, so a non-uuid path
        // segment would raise a Postgres cast error (a 500) instead of the
        // 404 every other miss produces.
        if (! Str::isUuid($quotationId)) {
            throw new NotFoundHttpException('Resource not found.');
        }

        $quotation = Quotation::query()
            ->with('booking.provider')
            ->where('id', $quotationId)
            ->where('booking_id', $this->guestBooking($request)->id)
            ->first();

        if ($quotation === null) {
            throw new NotFoundHttpException('Resource not found.');
        }

        return $quotation;
    }
}
