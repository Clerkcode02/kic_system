<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Payment;

use App\Domain\User\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class ListPayoutsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(PermissionName::PayoutsView->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string'],
            'provider_id' => ['sometimes', 'uuid'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
        ];
    }
}
