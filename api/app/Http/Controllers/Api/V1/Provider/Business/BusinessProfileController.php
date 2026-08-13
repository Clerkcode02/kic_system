<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Business;

use App\Domain\Business\Actions\UpdateBusinessProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\Business\UpdateBusinessProfileRequest;
use App\Http\Resources\BusinessResource;
use Illuminate\Http\Request;

class BusinessProfileController extends Controller
{
    public function show(Request $request): BusinessResource
    {
        $business = $request->user()->business;

        abort_if($business === null, 404);

        return new BusinessResource($business);
    }

    public function update(UpdateBusinessProfileRequest $request, UpdateBusinessProfile $action): BusinessResource
    {
        $business = $request->user()->business;

        // FormRequest::authorize() already rejected a request with no
        // business before validation ran — this narrows the type for static
        // analysis, not a reachable runtime path.
        abort_if($business === null, 403);

        return new BusinessResource($action->handle($business, $request->validated()));
    }
}
