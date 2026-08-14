<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;

class ReviseQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('revise', $this->route('quotation')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'labor_cost' => ['required', 'numeric', 'min:0'],
            'materials_cost' => ['required', 'numeric', 'min:0'],
            'additional_fees' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items' => ['nullable', 'array'],
            'line_items.*.description' => ['required_with:line_items', 'string', 'max:255'],
            'line_items.*.quantity' => ['required_with:line_items', 'numeric', 'min:0.01'],
            'line_items.*.unit_price' => ['required_with:line_items', 'numeric', 'min:0'],
        ];
    }
}
