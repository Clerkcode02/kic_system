<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Payment;

use Illuminate\Foundation\Http\FormRequest;

class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('refund', $this->route('payment')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Amount-vs-threshold and amount-vs-payment-total checks live in
            // IssueRefund (the enforcing copy) — omitting it here refunds
            // the full payment.
            'amount' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
