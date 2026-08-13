<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Business;

use App\Domain\Business\Actions\ReplaceProviderAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\Business\UpdateProviderAvailabilityRequest;
use App\Http\Resources\ProviderAvailabilityConfigResource;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function show(Request $request): ProviderAvailabilityConfigResource
    {
        $business = $request->user()->business;

        abort_if($business === null, 404);

        return new ProviderAvailabilityConfigResource(
            $business->load(['availability', 'availabilityOverrides'])
        );
    }

    public function update(UpdateProviderAvailabilityRequest $request, ReplaceProviderAvailability $action): ProviderAvailabilityConfigResource
    {
        $business = $request->user()->business;

        abort_if($business === null, 403);

        return new ProviderAvailabilityConfigResource($action->handle($business, $request->validated()));
    }
}
