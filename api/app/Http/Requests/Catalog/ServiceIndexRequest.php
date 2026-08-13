<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'string', 'max:255'],
            'lat' => ['sometimes', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'required_with:lat', 'numeric', 'between:-180,180'],
            'radius' => ['sometimes', 'integer', 'min:100', 'max:200000'],
            'sort' => ['sometimes', Rule::in(['newest', 'price_low', 'price_high'])],
            'cursor' => ['sometimes', 'string'],
        ];
    }
}
