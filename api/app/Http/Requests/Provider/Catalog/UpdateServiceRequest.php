<?php

declare(strict_types=1);

namespace App\Http\Requests\Provider\Catalog;

use App\Domain\Catalog\Enums\ServicePricingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('service')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'uuid', 'exists:categories,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'pricing_type' => ['sometimes', new Enum(ServicePricingType::class)],
            'base_price' => ['sometimes', 'numeric', 'min:0'],
            'estimated_duration_minutes' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'pricing_tiers' => ['sometimes', 'array'],
            'pricing_tiers.*.tier_name' => ['required_with:pricing_tiers', 'string', 'max:255'],
            'pricing_tiers.*.description' => ['nullable', 'string'],
            'pricing_tiers.*.price' => ['required_with:pricing_tiers', 'numeric', 'min:0'],
            'pricing_tiers.*.estimated_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'pricing_tiers.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
