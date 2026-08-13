<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Business;

use App\Domain\Business\Actions\SubmitForVerification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\Business\SubmitForVerificationRequest;
use App\Http\Resources\BusinessResource;

class SubmitForVerificationController extends Controller
{
    public function __invoke(SubmitForVerificationRequest $request, SubmitForVerification $action): BusinessResource
    {
        $business = $request->user()->business;

        abort_if($business === null, 403);

        return new BusinessResource($action->handle($business));
    }
}
