<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Payment;

use App\Domain\User\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class RetryFailedTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(PermissionName::PayoutsRetry->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
