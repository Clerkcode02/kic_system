<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterFreelancerRequest extends FormRequest
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

            'headline' => ['required', 'string', 'max:255'],
            'bio' => ['required', 'string'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'years_experience' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
