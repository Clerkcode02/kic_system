<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Verification;

use Illuminate\Foundation\Http\FormRequest;

class RejectBusinessVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('approve', $this->route('business'));
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
