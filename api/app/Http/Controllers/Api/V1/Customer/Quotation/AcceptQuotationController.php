<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Quotation;

use App\Domain\Quotation\Actions\AcceptQuotation;
use App\Domain\Quotation\Models\Quotation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\AcceptQuotationRequest;
use App\Http\Resources\QuotationResource;
use App\Support\ValueObjects\BookingActor;

class AcceptQuotationController extends Controller
{
    public function __invoke(AcceptQuotationRequest $request, Quotation $quotation, AcceptQuotation $action): QuotationResource
    {
        $result = $action->handle($quotation, BookingActor::user($request->user()));

        return (new QuotationResource($result['quotation']))->additional([
            'meta' => [
                'payment' => [
                    'id' => $result['payment']->id,
                    'stripe_payment_intent_id' => $result['payment']->stripe_payment_intent_id,
                    'client_secret' => $result['client_secret'],
                    'amount' => $result['payment']->amount->toDecimal(),
                    'status' => $result['payment']->status,
                ],
            ],
        ]);
    }
}
