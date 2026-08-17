<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Payment;

use App\Domain\Payment\Actions\RetryFailedMilestoneTransfer;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Queries\ListFailedTransferPaymentsQuery;
use App\Domain\User\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payment\RetryFailedTransferRequest;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FailedTransferController extends Controller
{
    public function index(Request $request, ListFailedTransferPaymentsQuery $query): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can(PermissionName::PayoutsView->value), 403);

        return PaymentResource::collection($query->handle());
    }

    public function retry(RetryFailedTransferRequest $request, Payment $payment, RetryFailedMilestoneTransfer $action): PaymentResource
    {
        return new PaymentResource($action->handle($payment, $request->user()));
    }
}
