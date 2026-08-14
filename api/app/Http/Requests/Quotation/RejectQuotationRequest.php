<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;

class RejectQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reject', $this->route('quotation')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
