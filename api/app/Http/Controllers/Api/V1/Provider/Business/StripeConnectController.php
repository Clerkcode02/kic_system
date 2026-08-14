<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Business;

use App\Domain\Payment\Services\StripeConnectService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\Business\StripeConnectRequest;
use Illuminate\Http\JsonResponse;

class StripeConnectController extends Controller
{
    public function __construct(private readonly StripeConnectService $connect)
    {
    }

    public function onboardingLink(StripeConnectRequest $request): JsonResponse
    {
        $business = $request->user()->business;

        $url = $this->connect->createOnboardingLink(
            $business,
            (string) config('services.stripe.connect_refresh_url'),
            (string) config('services.stripe.connect_return_url'),
        );

        return response()->json(['data' => ['url' => $url]]);
    }

    public function status(StripeConnectRequest $request): JsonResponse
    {
        $business = $request->user()->business;

        $status = $this->connect->syncStatus($business);

        return response()->json(['data' => $status]);
    }
}
