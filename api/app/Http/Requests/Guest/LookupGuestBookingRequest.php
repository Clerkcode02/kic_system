<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SRS §6.1: the lookup response must be identical for real and fake booking
 * numbers, so validation here is strictly about *shape* — never about
 * existence. No `exists:` rule, no per-field message that would let a
 * caller distinguish "that booking number is wrong" from "that email is".
 */
class LookupGuestBookingRequest extends FormRequest
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
            'booking_number' => ['required', 'string', 'max:64'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
