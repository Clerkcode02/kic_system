<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotation;

use App\Domain\Quotation\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [Quotation::class, $this->route('booking')]) ?? false;
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
            // Deliberately no 'total_amount'/'platform_fee'/'tax_amount' rule —
            // those are never accepted from the client (CLAUDE.md §2/§17),
            // and any such field in the payload is silently ignored by the
            // SendQuotation action, which recomputes them itself.
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items' => ['nullable', 'array'],
            'line_items.*.description' => ['required_with:line_items', 'string', 'max:255'],
            'line_items.*.quantity' => ['required_with:line_items', 'numeric', 'min:0.01'],
            'line_items.*.unit_price' => ['required_with:line_items', 'numeric', 'min:0'],
        ];
    }
}
