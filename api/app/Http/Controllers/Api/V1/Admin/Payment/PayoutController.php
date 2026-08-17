<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Payment;

use App\Domain\Payment\Queries\ListPayoutsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payment\ListPayoutsRequest;
use App\Http\Resources\PayoutResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PayoutController extends Controller
{
    public function index(ListPayoutsRequest $request, ListPayoutsQuery $query): AnonymousResourceCollection
    {
        return PayoutResource::collection(
            $query->handle($request->validated()),
        );
    }
}
