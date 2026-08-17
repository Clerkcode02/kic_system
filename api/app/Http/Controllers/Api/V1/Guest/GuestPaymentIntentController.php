<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Guest;

use App\Domain\Payment\Actions\CreatePaymentIntent;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SRS §6.1 "Payments": identical to the registered path — destination
 * charge with `application_fee_amount`, totals recomputed server-side from
 * persisted rows, `Idempotency-Key` required. The only difference is where
 * the payable comes from: the token names the booking, so the client can't
 * nominate one.
 */
class GuestPaymentIntentController extends Controller
{
    use ResolvesGuestBooking;

    public function __invoke(Request $request, CreatePaymentIntent $action): JsonResponse
    {
        $result = $action->handle(
            $this->guestBooking($request),
            $this->guestActor($request),
        );

        return (new PaymentResource($result['payment']))
            ->additional(['meta' => ['client_secret' => $result['client_secret']]])
            ->response()
            ->setStatusCode(201);
    }
}
