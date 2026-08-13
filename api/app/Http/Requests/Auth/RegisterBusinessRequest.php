<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterBusinessRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],

            'legal_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:255', 'unique:businesses,registration_number'],
            'business_hours' => ['required', 'array'],
            'max_bookings_per_day' => ['required', 'integer', 'min:1'],
        ];
    }
}
