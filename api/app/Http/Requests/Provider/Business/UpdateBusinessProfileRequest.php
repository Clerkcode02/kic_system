<?php

declare(strict_types=1);

namespace App\Http\Requests\Provider\Business;

use App\Domain\Business\Enums\CanadianProvince;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateBusinessProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = $this->user()?->business;

        if ($business === null) {
            return false;
        }

        return $this->user()?->can('manage', $business) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['sometimes', 'string', 'max:255'],
            'business_hours' => ['sometimes', 'array'],
            'max_bookings_per_day' => ['sometimes', 'integer', 'min:1'],

            'street' => ['sometimes', 'required_with:city,province,postal_code', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'city' => ['sometimes', 'required_with:street,province,postal_code', 'string', 'max:255'],
            'province' => ['sometimes', 'required_with:street,city,postal_code', new Enum(CanadianProvince::class)],
            // Canadian postal code, e.g. "M5V 3A8" or "M5V3A8".
            'postal_code' => [
                'sometimes', 'required_with:street,city,province', 'string',
                'regex:/^[A-Za-z]\d[A-Za-z][ -]?\d[A-Za-z]\d$/',
            ],

            'lat' => ['sometimes', 'required_with:lng', 'numeric', 'between:41.6,83.2'],
            'lng' => ['sometimes', 'required_with:lat', 'numeric', 'between:-141.1,-52.5'],
        ];
    }
}
