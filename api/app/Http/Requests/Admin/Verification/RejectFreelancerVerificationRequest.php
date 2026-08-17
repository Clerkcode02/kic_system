<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Verification;

use Illuminate\Foundation\Http\FormRequest;

class RejectFreelancerVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(\App\Domain\User\Enums\PermissionName::FreelancersApprove->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
