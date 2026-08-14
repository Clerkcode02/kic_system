<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Payment;

use App\Domain\Payment\Actions\IssueRefund;
use App\Domain\Payment\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payment\RefundPaymentRequest;
use App\Http\Resources\RefundResource;
use App\Support\ValueObjects\Money;

class RefundPaymentController extends Controller
{
    public function __invoke(RefundPaymentRequest $request, Payment $payment, IssueRefund $action): RefundResource
    {
        $amount = $request->validated('amount') !== null
            ? Money::fromDecimal((string) $request->validated('amount'), $payment->currency)
            : null;

        $refund = $action->handle($payment, $request->user(), $amount, $request->validated('reason'));

        return new RefundResource($refund);
    }
}
