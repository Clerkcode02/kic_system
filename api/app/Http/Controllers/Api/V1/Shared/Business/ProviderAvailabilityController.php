<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Business;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Services\AvailabilityService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProviderAvailabilityQueryRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class ProviderAvailabilityController extends Controller
{
    public function show(ProviderAvailabilityQueryRequest $request, Business $business, AvailabilityService $service): JsonResponse
    {
        $date = CarbonImmutable::createFromFormat('Y-m-d', $request->validated('date'));

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'slots' => $service->slotsForDate($business, $date),
            ],
        ]);
    }
}
