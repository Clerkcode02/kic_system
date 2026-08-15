<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Domain\Business\Enums\CanadianProvince;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state_province' => ['required', Rule::in(array_column(CanadianProvince::cases(), 'value'))],
            'postal_code' => ['required', 'string', 'max:16'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
