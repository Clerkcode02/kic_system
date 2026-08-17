<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Payment;

use App\Domain\Payment\Actions\CreatePaymentIntent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\CreatePaymentIntentRequest;
use App\Http\Resources\PaymentResource;
use App\Support\ValueObjects\BookingActor;
use Illuminate\Http\JsonResponse;

class CreatePaymentIntentController extends Controller
{
    public function __invoke(CreatePaymentIntentRequest $request, CreatePaymentIntent $action): JsonResponse
    {
        $payable = $request->resolvePayable();

        $result = $action->handle($payable, BookingActor::user($request->user()));

        return (new PaymentResource($result['payment']))
            ->additional(['meta' => ['client_secret' => $result['client_secret']]])
            ->response()
            ->setStatusCode(201);
    }
}
